<?php
/**
 * Password Hash Generator Utility
 * ================================
 * Usage (CLI): php scripts/generate-hash.php
 * Prompts without echoing the password; the password is never a CLI argument.
 *
 * Generates a bcrypt hash (cost 12) suitable for the users table.
 * Use this to set or reset admin passwords safely.
 */

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

if ($argc > 1) {
    fwrite(STDERR, "Do not pass passwords as command-line arguments. Run this utility without arguments.\n");
    exit(1);
}

function readHiddenPassword(): string
{
    if (PHP_OS_FAMILY === 'Windows') {
        $script = '$s=Read-Host -Prompt "Enter password" -AsSecureString; $p=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); try { [Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($p)) } finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($p) }';
        $pipes = [];
        $process = proc_open(
            ['powershell.exe', '-NoLogo', '-NoProfile', '-Command', $script],
            [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR],
            $pipes
        );
        if (!is_resource($process)) {
            fwrite(STDERR, "Unable to open PowerShell's secure password prompt.\n");
            exit(1);
        }
        $password = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        if (proc_close($process) !== 0 || !is_string($password)) {
            fwrite(STDERR, "Password prompt failed.\n");
            exit(1);
        }
        return $password;
    }

    $ttyState = trim((string) shell_exec('stty -g 2>/dev/null'));
    if ($ttyState === '') {
        fwrite(STDERR, "Run this utility from an interactive terminal.\n");
        exit(1);
    }
    shell_exec('stty -echo');
    try {
        fwrite(STDOUT, 'Enter password: ');
        $line = fgets(STDIN);
    } finally {
        shell_exec('stty ' . escapeshellarg($ttyState));
        fwrite(STDOUT, "\n");
    }
    return is_string($line) ? rtrim($line, "\r\n") : '';
}

$password = readHiddenPassword();

if (empty($password)) {
    echo "Error: Password cannot be empty.\n";
    exit(1);
}

// Validate password strength
$errors = [];
if (strlen($password) < 12) {
    $errors[] = "- Must be at least 12 bytes";
}
if (strlen($password) > 72) {
    $errors[] = "- Must be no more than 72 bytes (bcrypt limit)";
}
if (str_contains($password, "\0")) {
    $errors[] = "- Must not contain a null byte";
}
if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = "- Must contain at least one uppercase letter";
}
if (!preg_match('/[a-z]/', $password)) {
    $errors[] = "- Must contain at least one lowercase letter";
}
if (!preg_match('/[0-9]/', $password)) {
    $errors[] = "- Must contain at least one digit";
}
if (!preg_match('/[^A-Za-z0-9]/', $password)) {
    $errors[] = "- Must contain at least one special character";
}

if (!empty($errors)) {
    echo "Password does not meet strength requirements:\n";
    echo implode("\n", $errors) . "\n";
    exit(1);
}

// Generate hash
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verify immediately
if (!password_verify($password, $hash)) {
    echo "FATAL: Hash verification failed. Do not use this hash.\n";
    exit(1);
}

echo "\n";
echo "Password:  received securely\n";
echo "Algorithm: bcrypt (cost 12)\n";
echo "Hash:      {$hash}\n";
echo "Verified:  YES\n";
echo "\n";
echo "SQL snippet:\n";
echo "  UPDATE users SET password = '{$hash}', force_password_change = 1 WHERE email = '<ADMIN_EMAIL>';\n";
echo "\n";
