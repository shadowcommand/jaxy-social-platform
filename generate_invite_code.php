
<?php

/**
 * Generate a 32-character invitation code.
 *
 * @return string
 */
function generateInviteCode(): string
{
    // 16 random bytes => 32 hexadecimal characters
    return bin2hex(random_bytes(16));
}

$inviteCode = generateInviteCode();
echo $inviteCode;
?>