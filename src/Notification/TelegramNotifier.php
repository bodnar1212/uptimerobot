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

        if (!$textSent) {
            // If text message failed, don't try voice
            return false;
        }

        // Then send a voice call (voice message) - optional, don't fail if voice fails
        $voiceSent = $this->sendVoiceCall($monitor, $status, $previousStatus);
        
        if (!$voiceSent) {
            error_log("Telegram: Text message sent successfully, but voice call failed. This is non-critical.");
        }

        // Return true if text message was sent (voice is optional)
        return $textSent;
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
        
        // Telegram API accepts usernames without @ prefix
        $chatId = $this->chatId;
        if (strpos($chatId, '@') === 0) {
            $chatId = substr($chatId, 1);
        }
        
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ];

        return $this->makeRequest($url, $payload);
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
        
        // Telegram API accepts usernames without @ prefix
        $chatId = $this->chatId;
        if (strpos($chatId, '@') === 0) {
            $chatId = substr($chatId, 1);
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

    public function getType(): string
    {
        return 'telegram';
    }
}

