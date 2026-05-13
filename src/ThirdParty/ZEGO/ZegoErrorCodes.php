<?php

namespace ZEGO;

/**
 * ZEGO token generation error codes (from official server assistant).
 * @see https://github.com/ZEGOCLOUD/zego_server_assistant
 */
class ZegoErrorCodes
{
    public const success                       = 0;
    public const appIDInvalid                 = 1;
    public const userIDInvalid                 = 3;
    public const secretInvalid                 = 5;
    public const effectiveTimeInSecondsInvalid = 6;
}
