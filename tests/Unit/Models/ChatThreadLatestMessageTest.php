<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\ChatThread;
use Tests\TestCase;

/**
 * Guards the production 500 from #146.
 *
 * `latestOfMany()` / `ofMany()` always add `MAX(<primary key>)` to their sub-query
 * (`CanBeOneOfMany::ofMany()` forces the key in, whatever column you pass), and
 * `chat_messages.id` is a `uuid`. Postgres has no `max(uuid)`, so eager-loading the
 * chat-list preview blew up `GET /chats` with
 * `SQLSTATE[42883] function max(uuid) does not exist`.
 *
 * The suite runs on SQLite (`phpunit.xml`), which evaluates `MAX(<uuid>)` happily,
 * so no behavioural test can catch this. Assert the generated SQL instead — that
 * shape is identical on both drivers.
 */
class ChatThreadLatestMessageTest extends TestCase
{
    public function test_latest_message_never_aggregates_the_uuid_primary_key(): void
    {
        $sql = strtolower(str_replace('"', '', (new ChatThread)->latestMessage()->getQuery()->toSql()));

        $this->assertStringNotContainsString(
            'max(chat_messages.id)',
            $sql,
            'Postgres has no max(uuid): latestMessage() must never aggregate the primary key.',
        );

        $this->assertStringNotContainsString(
            'min(chat_messages.id)',
            $sql,
            'Postgres has no min(uuid): latestMessage() must never aggregate the primary key.',
        );

        $this->assertStringContainsString(
            'order by chat_messages.created_at desc',
            $sql,
            'latestMessage() must resolve the newest message by created_at.',
        );
    }
}
