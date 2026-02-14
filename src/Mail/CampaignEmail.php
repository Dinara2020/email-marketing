<?php

namespace Dinara\EmailMarketing\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlContent;
    public string $textContent;
    public ?string $unsubscribeUrl;
    public ?string $trackingId;

    public function __construct(
        string $htmlContent,
        string $subject,
        ?string $textContent = null,
        ?string $unsubscribeUrl = null,
        ?string $trackingId = null
    ) {
        $this->subject = $subject;
        $this->unsubscribeUrl = $unsubscribeUrl;
        $this->htmlContent = $this->wrapHtml($htmlContent, $subject);
        $this->textContent = $textContent ?: $this->htmlToText($htmlContent);
        $this->trackingId = $trackingId;
    }

    public function build(): self
    {
        $mail = $this->html($this->htmlContent)
            ->text('email-marketing::emails.plain', ['textContent' => $this->textContent]);

        // Add custom headers
        $mail->withSymfonyMessage(function ($message) {
            $headers = $message->getHeaders();

            // Message-ID
            $domain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
            $unique = $this->trackingId ?: uniqid('em_');
            $headers->addIdHeader('Message-ID', $unique . '@' . $domain);

            // Deliverability headers
            $headers->addTextHeader('X-Mailer', 'Laravel Email Marketing');
            $headers->addTextHeader('X-Priority', '3');

            // List-Unsubscribe (important for spam filters)
            if ($this->unsubscribeUrl) {
                $headers->addTextHeader('List-Unsubscribe', '<' . $this->unsubscribeUrl . '>');
                $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }
        });

        return $mail;
    }

    /**
     * Wrap HTML in proper email structure
     */
    protected function wrapHtml(string $html, string $subject): string
    {
        // If already has DOCTYPE, return as is
        if (stripos($html, '<!DOCTYPE') !== false) {
            return $html;
        }

        return '<!DOCTYPE html>
<html lang="ru" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>' . htmlspecialchars($subject) . '</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { color: #0066cc; text-decoration: none; }
        p { margin: 0 0 15px 0; }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, Helvetica, sans-serif;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="background-color: #ffffff; max-width: 600px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <tr>
                        <td style="padding: 30px 40px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.6; color: #333333;">
                            ' . $html . '
                        </td>
                    </tr>
                    ' . ($this->unsubscribeUrl ? '
                    <tr>
                        <td style="padding: 20px 40px; border-top: 1px solid #eeeeee; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.5; color: #999999; text-align: center;">
                            <a href="' . htmlspecialchars($this->unsubscribeUrl) . '" style="color: #999999; text-decoration: underline;">Отписаться от рассылки</a>
                        </td>
                    </tr>
                    ' : '') . '
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Convert HTML to plain text
     */
    protected function htmlToText(string $html): string
    {
        // Remove style and script tags
        $text = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html);
        $text = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $html);

        // Convert links to text with URL
        $text = preg_replace('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>([^<]+)<\/a>/i', '$2 ($1)', $text);

        // Convert line breaks
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n\n", $text);
        $text = preg_replace('/<\/div>/i', "\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n\n", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);
        $text = preg_replace('/<\/tr>/i', "\n", $text);

        // Remove all remaining tags
        $text = strip_tags($text);

        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        // Clean up whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }
}
