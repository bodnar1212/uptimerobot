<?php

namespace UptimeRobot\Notification;

use UptimeRobot\Entity\Monitor;
use UptimeRobot\Entity\MonitorStatus;

class TelegramNotifier implements NotificationInterface
{
    private string $botToken;
    private string $chatId;
    private string $apiUrl;

    public function __construct(string $botToken, string $chatId)
    {
        $this->botToken = $botToken;
        $this->chatId = $chatId;
        $this->apiUrl = "https://api.telegram.org/bot{$botToken}";
    }

    public function send(Monitor $monitor, MonitorStatus $status, ?string $previousStatus = null): bool
    {
        // First send a text message
        $textMessage = $this->buildMessage($monitor, $status, $previousStatus);
        $textSent = $this->sendMessage($textMessage);

        // Then send a voice message (voice file) - optional, don't fail if voice fails
        $voiceSent = $this->sendVoiceCall($monitor, $status, $previousStatus);

        // Finally, make an actual voice call using CallMeBot API - optional and independent
        $callSent = $this->makeVoiceCall($monitor, $status, $previousStatus);

        // Return true if at least one notification method succeeded
        return $textSent || $callSent;
    }

    private function buildMessage(Monitor $monitor, MonitorStatus $status, ?string $previousStatus): string
    {
        $statusEmoji = $status->getStatus() === 'up' ? '✅' : '❌';
        $statusText = strtoupper($status->getStatus());
        
        $message = "{$statusEmoji} *Monitor Alert*\n\n";
        $message .= "*URL:* {$monitor->getUrl()}\n";
        $message .= "*Status:* {$statusText}\n";
        $message .= "*Response Time:* {$status->getResponseTimeMs()} ms\n";
        
        if ($status->getHttpStatusCode()) {
            $message .= "*HTTP Status:* {$status->getHttpStatusCode()}\n";
        }
        
        if ($status->getErrorMessage()) {
            $errorMsg = substr($status->getErrorMessage(), 0, 200);
            $message .= "*Error:* {$errorMsg}\n";
        }
        
        $message .= "\n*Checked at:* " . $status->getCheckedAt()->format('Y-m-d H:i:s');
        
        if ($previousStatus && $previousStatus !== $status->getStatus()) {
            $message .= "\n*Previous Status:* " . strtoupper($previousStatus);
        }

        return $message;
    }

    private function sendMessage(string $text): bool
    {
        $url = $this->apiUrl . '/sendMessage';
        
        // Telegram Bot API requires numeric chat ID for sending messages
        // If username format is used, try to get numeric ID from bot's messages
        $chatId = $this->chatId;
        if (strpos($chatId, '@') === 0 || !is_numeric($chatId)) {
            // Try to get numeric chat ID from bot's recent messages
            $numericChatId = $this->getNumericChatIdFromUsername($chatId);
            if ($numericChatId) {
                $chatId = $numericChatId;
            } else {
                // Remove @ prefix and try username format (may not work)
                $chatId = str_replace('@', '', $chatId);
            }
        }
        
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        return $this->makeRequest($url, $payload);
    }
    
    private function getNumericChatIdFromUsername(string $usernameOrChatId): ?string
    {
        // Remove @ prefix if present
        $username = str_replace('@', '', $usernameOrChatId);
        
        // Get updates from bot
        $url = $this->apiUrl . '/getUpdates?limit=100';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['ok']) || !$data['ok'] || empty($data['result'])) {
            return null;
        }
        
        // Search for messages from user with matching username
        foreach ($data['result'] as $update) {
            if (isset($update['message']['from']['username']) && 
                str_replace('@', '', $update['message']['from']['username']) === $username) {
                return (string)$update['message']['chat']['id'];
            }
        }
        
        return null;
    }

    private function sendVoiceCall(Monitor $monitor, MonitorStatus $status, ?string $previousStatus): bool
    {
        // Generate voice message text
        $voiceText = $this->buildVoiceText($monitor, $status, $previousStatus);
        
        // Generate voice file from text using TTS
        $voiceFile = $this->generateVoiceFile($voiceText);
        
        if (!$voiceFile || !file_exists($voiceFile)) {
            error_log("Telegram: Failed to generate voice file");
            return false;
        }

        // Send voice message
        $url = $this->apiUrl . '/sendVoice';
        
        // Use absolute path for CURLFile
        $voiceFilePath = realpath($voiceFile);
        if (!$voiceFilePath) {
            error_log("Telegram: Could not resolve voice file path");
            unlink($voiceFile);
            return false;
        }
        
        // Telegram Bot API requires numeric chat ID for sending messages
        // If username format is used, try to get numeric ID from bot's messages
        $chatId = $this->chatId;
        if (strpos($chatId, '@') === 0 || !is_numeric($chatId)) {
            // Try to get numeric chat ID from bot's recent messages
            $numericChatId = $this->getNumericChatIdFromUsername($chatId);
            if ($numericChatId) {
                $chatId = $numericChatId;
            } else {
                // Remove @ prefix and try username format (may not work)
                $chatId = str_replace('@', '', $chatId);
            }
        }
        
        $payload = [
            'chat_id' => $chatId,
            'voice' => new \CURLFile($voiceFilePath),
        ];

        $result = $this->makeRequest($url, $payload, true);
        
        // Clean up temporary file
        if (file_exists($voiceFile)) {
            unlink($voiceFile);
        }

        return $result;
    }

    private function makeVoiceCall(Monitor $monitor, MonitorStatus $status, ?string $previousStatus): bool
    {
        // CallMeBot API requires Telegram username (not chat ID)
        // Extract username from chat ID if it's a username, otherwise try to get it from bot updates
        $chatId = $this->chatId;
        $username = null;
        
        if (strpos($chatId, '@') === 0) {
            $username = substr($chatId, 1);
        } elseif (is_numeric($chatId)) {
            // For numeric chat IDs, try to get username from bot's recent messages
            $username = $this->getUsernameFromChatId($chatId);
            if (!$username) {
                error_log("Telegram: Voice call skipped - CallMeBot requires Telegram username, but chat ID is numeric and username not found. Use @username format for voice calls, or ensure you've messaged the bot.");
                return false;
            }
        } else {
            // Assume it's already a username without @
            $username = $chatId;
        }
        
        // Build call message text
        $callText = $this->buildVoiceText($monitor, $status, $previousStatus);
        
        // CallMeBot API endpoint
        // Note: You need to authorize CallMeBot first by messaging @CallMeBot_txtbot
        // Free tier limits: 50 messages per 240 minutes (4 hours), 30 second call duration
        // Paid tier ($15/month): Unlimited calls, longer duration, no delays
        // Try HTTPS first, fallback to HTTP
        $apiUrls = [
            "https://api.callmebot.com/start.php",
            "http://api.callmebot.com/start.php"
        ];
        
        $params = [
            'user' => '@' . $username,
            'text' => $callText,
            'lang' => 'en-US-Standard-C', // English voice
        ];
        
        $response = null;
        $httpCode = 0;
        $error = null;
        
        foreach ($apiUrls as $apiUrl) {
            $url = $apiUrl . '?' . http_build_query($params);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false, // CallMeBot might have SSL issues
                CURLOPT_SSL_VERIFYHOST => false,
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // If we got a response (even if error), break
            if ($httpCode > 0 || !empty($response)) {
                break;
            }
        }
        
        if ($httpCode >= 200 && $httpCode < 300) {
            // CallMeBot returns HTML page on success, check if it contains success indicators
            if (strpos($response, 'success') !== false || strpos($response, 'calling') !== false || strlen($response) > 100) {
                // Call initiated successfully
                return true;
            }
        }
        
        // Check response
        if ($httpCode >= 200 && $httpCode < 300) {
            // HTTP 200 but might be an error page, check response
            if (strpos(strtolower($response), 'error') !== false || strpos(strtolower($response), 'not authorized') !== false) {
                error_log("Telegram: CallMeBot authorization required. Message @CallMeBot_txtbot with /start");
                return false;
            }
            return true;
        } elseif ($httpCode === 400 || $httpCode === 401) {
            error_log("Telegram: CallMeBot authorization required. Message @CallMeBot_txtbot with /start");
        }
        
        return false;
    }

    private function buildVoiceText(Monitor $monitor, MonitorStatus $status, ?string $previousStatus): string
    {
        $statusText = $status->getStatus() === 'up' ? 'is up' : 'is down';
        $url = parse_url($monitor->getUrl(), PHP_URL_HOST) ?: $monitor->getUrl();
        
        $text = "Monitor alert. {$url} {$statusText}.";
        
        if ($status->getStatus() === 'down') {
            $text .= " Response time: {$status->getResponseTimeMs()} milliseconds.";
            if ($status->getErrorMessage()) {
                $errorMsg = substr($status->getErrorMessage(), 0, 100);
                $text .= " Error: {$errorMsg}.";
            }
        } else {
            $text .= " Response time: {$status->getResponseTimeMs()} milliseconds.";
        }

        return $text;
    }

    private function generateVoiceFile(string $text): ?string
    {
        // Escape text for shell command
        $escapedText = escapeshellarg($text);
        $tempFile = sys_get_temp_dir() . '/telegram_voice_' . uniqid() . '.ogg';
        
        // Try espeak first (common on Linux/Docker)
        $espeakPath = shell_exec("which espeak 2>/dev/null");
        $opusencPath = shell_exec("which opusenc 2>/dev/null");
        
        if ($espeakPath && $opusencPath) {
            // Use espeak with opusenc
            $espeakPath = trim($espeakPath);
            $opusencPath = trim($opusencPath);
            $cmd = "{$espeakPath} -s 150 -v en {$escapedText} --stdout 2>/dev/null | {$opusencPath} --quiet - \"{$tempFile}\" 2>&1";
            $output = shell_exec($cmd);
            
            if (file_exists($tempFile) && filesize($tempFile) > 0) {
                return $tempFile;
            }
            
            // If that didn't work, try without piping (save to wav first, then convert)
            $wavFile = sys_get_temp_dir() . '/telegram_voice_' . uniqid() . '.wav';
            $cmd1 = "{$espeakPath} -s 150 -v en {$escapedText} -w \"{$wavFile}\" 2>&1";
            shell_exec($cmd1);
            
            if (file_exists($wavFile) && filesize($wavFile) > 0) {
                // Convert wav to ogg using opusenc
                $cmd2 = "{$opusencPath} --quiet \"{$wavFile}\" \"{$tempFile}\" 2>&1";
                shell_exec($cmd2);
                unlink($wavFile);
                
                if (file_exists($tempFile) && filesize($tempFile) > 0) {
                    return $tempFile;
                }
            }
        }
        
        // Try using say (macOS) if available
        $sayPath = shell_exec("which say 2>/dev/null");
        if ($sayPath) {
            $sayPath = trim($sayPath);
            $aiffFile = $tempFile . '.aiff';
            $cmd = "{$sayPath} -v Samantha {$escapedText} -o \"{$aiffFile}\" 2>&1";
            shell_exec($cmd);
            
            if (file_exists($aiffFile) && filesize($aiffFile) > 0) {
                // Try to convert to ogg if ffmpeg is available
                $ffmpegPath = shell_exec("which ffmpeg 2>/dev/null");
                if ($ffmpegPath) {
                    $ffmpegPath = trim($ffmpegPath);
                    $cmd = "{$ffmpegPath} -i \"{$aiffFile}\" -acodec libopus \"{$tempFile}\" -y 2>&1";
                    shell_exec($cmd);
                    unlink($aiffFile);
                    
                    if (file_exists($tempFile) && filesize($tempFile) > 0) {
                        return $tempFile;
                    }
                } else {
                    // Use aiff as fallback (Telegram accepts various formats)
                    rename($aiffFile, $tempFile);
                    return $tempFile;
                }
            }
        }
        
        error_log("Telegram: No TTS engine found or TTS generation failed. Voice call will be skipped.");
        return null;
    }

    private function makeRequest(string $url, array $payload, bool $isMultipart = false): bool
    {
        $ch = curl_init($url);
        
        if ($isMultipart) {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
        } else {
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $responseData = json_decode($response, true);
            if (isset($responseData['ok']) && $responseData['ok']) {
                return true;
            } else {
                // Log detailed error from Telegram API
                $errorDesc = $responseData['description'] ?? 'Unknown error';
                $errorCode = $responseData['error_code'] ?? 'Unknown';
                error_log("Telegram API error: [{$errorCode}] {$errorDesc}");
                
                // Provide helpful error messages for common issues
                if (strpos($errorDesc, "bots can't send messages to bots") !== false) {
                    error_log("Telegram: Chat ID appears to be a bot ID. Make sure you're using your personal user ID, not a bot ID.");
                } elseif (strpos($errorDesc, "chat not found") !== false) {
                    error_log("Telegram: Chat not found. Make sure you've started a conversation with your bot first by sending it a message.");
                } elseif (strpos($errorDesc, "unauthorized") !== false) {
                    error_log("Telegram: Bot token is invalid. Please check your bot token.");
                }
            }
        }

        error_log("Telegram API request failed: HTTP {$httpCode} - {$error} - Response: {$response}");
        return false;
    }

    private function getUsernameFromChatId(string $numericChatId): ?string
    {
        // Try to get username from bot's recent updates
        $url = $this->apiUrl . '/getUpdates?limit=100';
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['ok']) || !$data['ok'] || empty($data['result'])) {
            return null;
        }
        
        // Search for messages from the matching chat ID
        foreach ($data['result'] as $update) {
            if (isset($update['message']['chat']['id']) && 
                (string)$update['message']['chat']['id'] === $numericChatId) {
                $from = $update['message']['from'] ?? null;
                if ($from && isset($from['username']) && !empty($from['username'])) {
                    return $from['username'];
                }
            }
        }
        
        return null;
    }

    public function getType(): string
    {
        return 'telegram';
    }
}

