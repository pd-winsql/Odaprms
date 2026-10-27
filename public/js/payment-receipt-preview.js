(() => {
    const image=document.getElementById('paymentReceiptImage');
    const minus=document.getElementById('receiptZoomOut'),plus=document.getElementById('receiptZoomIn');
    let zoom=1;
    const update=()=>{
        const base=Math.min(800,document.querySelector('main').clientWidth-(innerWidth<=575?24:48));
        image.style.width=Math.round(base*zoom)+'px';
        document.getElementById('receiptZoomLevel').textContent=Math.round(zoom*100)+'%';
        minus.disabled=zoom<=.5; plus.disabled=zoom>=3;
    };
    minus.onclick=()=>{zoom=Math.max(.5,zoom-.25);update();};
    plus.onclick=()=>{zoom=Math.min(3,zoom+.25);update();};
    document.getElementById('receiptZoomReset').onclick=()=>{zoom=1;update();};
    window.addEventListener('resize',update); update();
})();
