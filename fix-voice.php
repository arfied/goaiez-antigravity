<?php
$content = file_get_contents('app/app/Services/Voice/VoiceCalls.php');
$target = <<<PHP
            if (\$eventType instanceof VoiceEventType
                && \$eventType->owesTextBack()
                && \$this->forwarding->modeFor()->touchesLiveCalls()
            ) {
                CallMissed::dispatch(\$businessId, \$this->inboundCall(\$facts, \$customer, \$eventType));
            }

            return VoiceIngestOutcome::Recorded;
PHP;
$replacement = <<<PHP
            try {
                if (\$eventType instanceof VoiceEventType
                    && \$eventType->owesTextBack()
                    && \$this->forwarding->modeFor()->touchesLiveCalls()
                ) {
                    CallMissed::dispatch(\$businessId, \$this->inboundCall(\$facts, \$customer, \$eventType));
                }
            } catch (\Throwable \$e) {
                \Illuminate\Support\Facades\Log::error("EXCEPTION IN WRITE: " . \$e->getMessage());
                throw \$e;
            }

            return VoiceIngestOutcome::Recorded;
PHP;
$content = str_replace($target, $replacement, $content);
file_put_contents('app/app/Services/Voice/VoiceCalls.php', $content);
