<?php
require_once __DIR__ . '/../apps/support/PasswordPolicy.php';

$cases = [
    'Abcd12!' => false,
    'abcd123!' => false,
    'ABCD123!' => false,
    'Abcdefg!' => false,
    'Abcd1234' => false,
    'Abcd123 ' => false,
    'Abcd123!' => true,
    'Longer Password123@' => true,
];
foreach ($cases as $password => $expected) {
    if (PasswordPolicy::isValid($password) !== $expected) {
        throw new RuntimeException('Password policy case failed.');
    }
}
foreach (str_split('!"#$%&\'()*+,-./:;<=>?@[\\]^_`{|}~') as $symbol) {
    if (!PasswordPolicy::isValid('Abcd123' . $symbol)) {
        throw new RuntimeException('Special-character case failed.');
    }
}
echo "PASS: Length, uppercase, lowercase, number, and special-character rules.\n";
