<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealQueue extends Model
{
    protected $table = 'deals_queue';

    // Разрешаем массовое заполнение этих полей
    protected $fillable = [
        'external_id',
        'source_type',
        'status',
        'payload',
        'attempts',
        'error_log'
    ];

    // Магия: Eloquent сам будет делать json_encode/decode для этого поля
    protected $casts = [
        'payload' => 'array',
    ];
}