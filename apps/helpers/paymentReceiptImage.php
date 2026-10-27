<?php
/** Render only immutable settlement snapshots; never read browser-supplied amounts. */
function vdPaymentReceiptPng(array $receipt): string {
    if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('PNG receipts require the PHP GD extension.');
    $font=$_ENV['RECEIPT_FONT_PATH'] ?? (PHP_OS_FAMILY==='Windows' ? 'C:/Windows/Fonts/georgia.ttf' : '/usr/share/fonts/truetype/dejavu/DejaVuSerif.ttf');
    if (!is_file($font)) throw new RuntimeException('Receipt font is unavailable. Configure RECEIPT_FONT_PATH.');
    // Georgia on Windows lacks the peso glyph; use a Unicode-capable amount font.
    $amountFont=$_ENV['RECEIPT_AMOUNT_FONT_PATH'] ?? (PHP_OS_FAMILY==='Windows' ? 'C:/Windows/Fonts/segoeui.ttf' : $font);
    if (!is_file($amountFont)) throw new RuntimeException('Receipt amount font is unavailable. Configure RECEIPT_AMOUNT_FONT_PATH.');
    $width=1200; $rows=[];
    $wrap=static function(string $text, int $size, int $maxWidth) use($font): array {
        $lines=[''];
        foreach (preg_split('//u',$text,-1,PREG_SPLIT_NO_EMPTY) as $character) {
            $index=count($lines)-1; $candidate=$lines[$index].$character;
            $box=imagettfbbox($size,0,$font,$candidate);
            if ($box[2]-$box[0]>$maxWidth && $lines[$index]!=='') $lines[]=$character;
            else $lines[$index]=$candidate;
        }
        return $lines;
    };
    foreach ($receipt['items'] as $item) {
        $lines=$wrap($item['name'],22,450);
        $rows[]=['item'=>$item,'lines'=>$lines,'height'=>max(64,count($lines)*32+24)];
    }
    $patientLines=$wrap('Patient: '.$receipt['patient'],24,1000);
    $clinicLines=$wrap($receipt['clinic'],26,1000);
    $actorLines=$wrap('Recorded by: '.$receipt['recorded_by'],20,1000);
    $extra=(count($patientLines)+count($clinicLines)-2)*34;
    $height=1300+$extra+array_sum(array_column($rows,'height'))+count($actorLines)*28;
    $image=imagecreatetruecolor($width,$height);
    $white=imagecolorallocate($image,255,255,255); $gold=imagecolorallocate($image,128,96,30);
    $ink=imagecolorallocate($image,38,34,29); $pale=imagecolorallocate($image,247,241,230);
    imagefill($image,0,0,$white);
    $text=static function(string $value,int $x,int $y,int $size=22,?int $color=null) use($image,$font,$ink) {
        imagettftext($image,$size,0,$x,$y,$color??$ink,$font,$value);
    };
    $right=static function(string $value,int $x,int $y,int $size=22) use($amountFont,$image,$ink) {
        $box=imagettfbbox($size,0,$amountFont,$value);
        imagettftext($image,$size,0,$x-($box[2]-$box[0]),$y,$ink,$amountFont,$value);
    };
    $rule=static function(int $y) use($image,$gold) { imageline($image,60,$y,1140,$y,$gold); };
    $money=static fn($value)=>'₱'.number_format((float)$value,2);
    $logo=!empty($receipt['logo']) ? @imagecreatefromstring(base64_decode($receipt['logo'],true)) : false;
    if ($logo) {
        $scale=min(500/imagesx($logo),190/imagesy($logo)); $w=(int)(imagesx($logo)*$scale); $h=(int)(imagesy($logo)*$scale);
        imagecopyresampled($image,$logo,(int)(($width-$w)/2),35,0,0,$w,$h,imagesx($logo),imagesy($logo)); imagedestroy($logo);
    } else {
        foreach ($wrap($receipt['brand'],30,1000) as $i=>$line) $text($line,70,90+$i*42,30,$gold);
    }
    $rule(250); $text('Payment Receipt',60,325,42); $text('PAID',1010,320,27,$gold);
    $y=385;
    foreach ($clinicLines as $line) { $text($line,60,$y,26); $y+=34; }
    $text('Receipt no.: '.$receipt['number'],60,$y+18); $y+=60;
    $text('Appointment #'.$receipt['appointment_id'],60,$y); $y+=44;
    $date=new DateTimeImmutable($receipt['settled_at'],new DateTimeZone('Asia/Manila'));
    $text('Settled: '.$date->format('F j, Y · g:i A').' (PHT)',60,$y); $y+=44;
    foreach ($patientLines as $line) { $text($line,60,$y,24); $y+=34; }
    $rule($y+20); $y+=70; $text('TREATMENT BREAKDOWN',60,$y,24,$gold); $y+=25;
    imagefilledrectangle($image,60,$y,1140,$y+58,$pale);
    $text('Treatment / Service',75,$y+38,22); $right('Qty',690,$y+38); $right('Rate',905,$y+38); $right('Amount',1125,$y+38);
    $y+=58;
    foreach ($rows as $row) {
        $item=$row['item']; foreach ($row['lines'] as $i=>$line) $text($line,75,$y+38+$i*32);
        $right(rtrim(rtrim(number_format((float)$item['quantity'],2,'.',''),'0'),'.'),690,$y+38);
        $right($item['unit_price']===null?'Not itemized':$money($item['unit_price']),905,$y+38,20);
        $right($item['unit_price']===null?'—':$money(round((float)$item['quantity']*(float)$item['unit_price'],2)),1125,$y+38,20);
        $y+=$row['height']; $rule($y);
    }
    $y+=55;
    foreach ([['Treatment total',$receipt['total']],['Deposit applied',-$receipt['deposit']],['Final payment (Cash)',$receipt['payment']],['Cash tendered',$receipt['tendered']],['Change',$receipt['change']]] as [$label,$amount]) {
        $text($label,590,$y,22); $right(($amount<0?'−':'').$money(abs($amount)),1125,$y,22); $y+=45;
    }
    $rule($y); $y+=58; $text('Outstanding balance',60,$y,28); $right($money(0),1125,$y,30); $y+=30; $rule($y); $y+=55;
    foreach ($actorLines as $line) { $text($line,60,$y,20); $y+=28; }
    $y+=35; $text('Thank you for trusting us with your smile.',60,$y,23,$gold);
    $text('System-generated payment acknowledgment.',60,$y+40,18);
    ob_start(); imagepng($image); $bytes=ob_get_clean(); imagedestroy($image);
    if (!$bytes) throw new RuntimeException('Unable to render receipt.');
    return $bytes;
}
