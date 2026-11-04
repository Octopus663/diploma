<?php

namespace App\LeakDemo;

use Illuminate\Http\Request;

/**
 * АНТИПАТЕРН 2: синглтон, у конструктор якого інжектовано Request.
 *
 * Синглтон створюється при першому запиті воркера і запам'ятовує ТОЙ запит.
 * Усі наступні запити цього воркера бачать чужі дані (ім'я, користувача, IP).
 * Це не лише витік пам'яті, а й витік даних між користувачами.
 */
class RequestContext
{
    public function __construct(private readonly Request $request) {}

    public function name(): string
    {
        return (string) $this->request->query('name', 'anonymous');
    }
}
