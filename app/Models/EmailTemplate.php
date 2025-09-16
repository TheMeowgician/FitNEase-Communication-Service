<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $primaryKey = 'template_id';

    protected $fillable = [
        'template_name',
        'template_type',
        'subject_template',
        'html_template',
        'text_template',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function replaceVariables(array $data): array
    {
        $subject = $this->subject_template;
        $html = $this->html_template;
        $text = $this->text_template;

        foreach ($data as $key => $value) {
            $placeholder = '{{' . $key . '}}';
            $subject = str_replace($placeholder, $value, $subject);
            $html = str_replace($placeholder, $value, $html);
            $text = str_replace($placeholder, $value, $text);
        }

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }
}
