<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/functions.php';
require APP_ROOT . '/app/actions.php';

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . PHP_EOL;
}

function expect_password_rejected(string $password, string $message): void
{
    try {
        assert_password_policy($password);
    } catch (RuntimeException) {
        echo 'PASS: ' . $message . PHP_EOL;
        return;
    }
    throw new RuntimeException('FAIL: ' . $message);
}

expect(e('<script>alert("x")</script> &') === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp;', 'HTML output is escaped');
expect(format_utc(null) === 'No deadline set', 'unset dates render safely');
expect(format_utc('2026-11-01 00:00:00') === 'Nov 1, 2026 12:00 AM UTC', 'dates render in UTC');
expect(safe_local_link('/?page=dashboard') === '/?page=dashboard', 'local notification links are preserved');
expect(safe_local_link('//outside.example') === '/', 'protocol-relative notification links are rejected');
expect(parse_utc_datetime_input('2026-11-11T12:30') === '2026-11-11 12:30:00', 'deadline inputs are normalized as UTC');

try {
    parse_utc_datetime_input('2026-99-99T27:80');
    throw new RuntimeException('FAIL: invalid deadline values are rejected');
} catch (RuntimeException $exception) {
    expect($exception->getMessage() === 'Enter a valid UTC date and time.', 'invalid deadline values are rejected');
}
expect(format_utc('invalid-date-string') === 'Invalid date', 'invalid dates handle gracefully');

$_POST['test_trim'] = '  clean string  ';
expect(post_string('test_trim') === 'clean string', 'post_string trims surrounding whitespace');
unset($_POST['test_trim']);

assert_password_policy('Portal#2026Secure');
echo 'PASS: a policy-compliant password is accepted' . PHP_EOL;
expect_password_rejected('Short#1', 'short passwords are rejected');
expect_password_rejected(str_repeat('a', 72) . 'A#1', 'bcrypt-overlength passwords are rejected');
expect_password_rejected("Portal#2026\0Secure", 'null-byte passwords are rejected');
