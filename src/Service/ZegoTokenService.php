<?php

namespace App\Service;

use ZEGO\ZegoAssistantToken;
use ZEGO\ZegoErrorCodes;
use ZEGO\ZegoServerAssistant;

/**
 * Generates ZEGO Token04 using the official ZEGO server assistant implementation.
 * Ensures token format matches ZEGO servers exactly (avoids "token authentication error").
 *
 * @see https://github.com/ZEGOCLOUD/zego_server_assistant/tree/master/token/php
 * @see https://www.zegocloud.com/docs/uikit/callkit-web/authentication-and-kit-token
 */
class ZegoTokenService
{
    public function __construct(
        private readonly int    $appId,
        private readonly string $serverSecret,
    ) {}

    /**
     * Generate a Token04 for the given user and room.
     * Uses RTC room payload (room_id + login/publish privilege) so joining the room is allowed.
     *
     * @param string $userId     ZEGO user ID (we use user's numeric id as string)
     * @param string $roomId     Room identifier, e.g. "room_contract_42"
     * @param int    $ttlSeconds Token validity in seconds (default 1 hour)
     *
     * @return string Token04 string for ZegoUIKitPrebuilt.generateKitTokenForProduction()
     *
     * @throws \RuntimeException if token generation fails
     */
    public function generateToken(string $userId, string $roomId, int $ttlSeconds = 3600): string
    {
        $payload = json_encode([
            'room_id'        => $roomId,
            'privilege'      => [
                1 => 1, // PrivilegeKeyLogin => PrivilegeEnable
                2 => 1, // PrivilegeKeyPublish => PrivilegeEnable
            ],
            'stream_id_list' => [],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $result = ZegoServerAssistant::generateToken04(
            $this->appId,
            $userId,
            $this->serverSecret,
            $ttlSeconds,
            $payload
        );

        if (!$result instanceof ZegoAssistantToken || $result->code !== ZegoErrorCodes::success) {
            $msg = $result instanceof ZegoAssistantToken ? $result->message : 'Token generation failed';
            throw new \RuntimeException('ZEGO token error: ' . $msg);
        }

        return $result->token;
    }

    /**
     * Build the standard room ID for a contract.
     */
    public static function roomIdForContract(int $contractId): string
    {
        return 'room_contract_' . $contractId;
    }

    public function getAppId(): int
    {
        return $this->appId;
    }
}
