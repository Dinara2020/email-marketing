<?php

namespace Dinara\EmailMarketing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'body_html',
        'body_text',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Available template variables
     */
    public static function getAvailableVariables(): array
    {
        return [
            '{{hotel_name}}' => 'Название отеля',
            '{{contact_name}}' => 'Имя контактного лица',
            '{{contact_email}}' => 'Email отеля',
            '{{hotel_city}}' => 'Город отеля',
            '{{hotel_address}}' => 'Адрес отеля',
            '{{current_date}}' => 'Текущая дата',
            '{{sender_name}}' => 'Имя отправителя',
            '{{sender_company}}' => 'Название компании',
            '{{logo_url}}' => 'URL логотипа компании',
            '{{site_url}}' => 'Ссылка на сайт',
            '{{site_name}}' => 'Название сайта',
        ];
    }

    public function campaigns()
    {
        return $this->hasMany(EmailCampaign::class, 'template_id');
    }

    /**
     * Replace variables in template
     */
    public function render(array $data): array
    {
        $html = $this->body_html;
        $text = $this->body_text ?? strip_tags($this->body_html);
        $subject = $this->subject;

        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $html = str_replace($placeholder, $value ?? '', $html);
            $text = str_replace($placeholder, $value ?? '', $text);
            $subject = str_replace($placeholder, $value ?? '', $subject);
        }

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }
}
