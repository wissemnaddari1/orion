<?php

namespace ZEGO;

/**
 * Official ZEGO Token04 generator (from ZEGOCLOUD/zego_server_assistant).
 * Used verbatim so token format matches ZEGO servers exactly.
 * @see https://github.com/ZEGOCLOUD/zego_server_assistant/tree/master/token/php/token04-php5.6%2B
 */
class ZegoServerAssistant
{
    private static function makeNonce(): int
    {
        return random_int(0, 2147483647);
    }

    private static function makeRandomIv(int $number = 16): string
    {
        $str = '0123456789abcdefghijklmnopqrstuvwxyz';
        $result = [];
        $strLen = strlen($str);
        for ($i = 0; $i < $number; $i++) {
            $result[] = $str[random_int(0, $strLen - 1)];
        }
        return implode('', $result);
    }

    /**
     * Generate Token04 for ZEGO authentication.
     *
     * @param int    $appId                    App ID from ZEGO console
     * @param string $userId                   User ID (string)
     * @param string $secret                   ServerSecret (must be 32 bytes for AES-256)
     * @param int    $effectiveTimeInSeconds   Token TTL in seconds
     * @param string $payload                 JSON string (e.g. RTC room payload with room_id, privilege)
     * @return ZegoAssistantToken
     */
    public static function generateToken04(int $appId, string $userId, string $secret, int $effectiveTimeInSeconds, string $payload): ZegoAssistantToken
    {
        $assistantToken = new ZegoAssistantToken();
        $assistantToken->code = ZegoErrorCodes::success;

        if ($appId === 0) {
            $assistantToken->code = ZegoErrorCodes::appIDInvalid;
            $assistantToken->message = 'appID invalid';
            return $assistantToken;
        }

        if ($userId === '') {
            $assistantToken->code = ZegoErrorCodes::userIDInvalid;
            $assistantToken->message = 'userID invalid';
            return $assistantToken;
        }

        $keyLen = strlen($secret);
        if ($keyLen !== 16 && $keyLen !== 24 && $keyLen !== 32) {
            $assistantToken->code = ZegoErrorCodes::secretInvalid;
            $assistantToken->message = 'secret must be a 16, 24, or 32 byte string';
            return $assistantToken;
        }

        if ($effectiveTimeInSeconds <= 0) {
            $assistantToken->code = ZegoErrorCodes::effectiveTimeInSecondsInvalid;
            $assistantToken->message = 'effectiveTimeInSeconds invalid';
            return $assistantToken;
        }

        $timestamp = time();
        $nonce = self::makeNonce();
        $data = [
            'app_id'  => $appId,
            'user_id' => $userId,
            'nonce'   => $nonce,
            'ctime'   => $timestamp,
            'expire'  => $timestamp + $effectiveTimeInSeconds,
            'payload' => $payload,
        ];

        $cipher = 'aes-128-cbc';
        switch ($keyLen) {
            case 16:
                $cipher = 'aes-128-cbc';
                break;
            case 24:
                $cipher = 'aes-192-cbc';
                break;
            case 32:
                $cipher = 'aes-256-cbc';
                break;
            default:
                throw new \InvalidArgumentException('secret length not supported');
        }

        $plaintext = json_encode($data, JSON_BIGINT_AS_STRING);
        $iv = self::makeRandomIv(16);

        $encrypted = openssl_encrypt($plaintext, $cipher, $secret, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            $assistantToken->code = -1;
            $assistantToken->message = 'openssl_encrypt failed';
            return $assistantToken;
        }

        $packData = [
            strlen($iv),
            $iv,
            strlen($encrypted),
            $encrypted,
        ];

        $binary = pack('J', $data['expire']);
        $binary .= pack('na*na*', ...$packData);

        $assistantToken->token = '04' . base64_encode($binary);
        return $assistantToken;
    }
}
