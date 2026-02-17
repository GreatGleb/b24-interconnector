<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDealsQueueTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('deals_queue');
        $table->addColumn('external_id', 'integer')          // ID сделки из Битрикс24
        ->addColumn('source_type', 'string', ['limit' => 20]) // 'source' или 'vinipol'
        ->addColumn('status', 'enum', ['values' => ['pending', 'processing', 'done', 'error'], 'default' => 'pending'])
            ->addColumn('payload', 'json', ['null' => true])   // Данные вебхука
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('error_log', 'text', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['null' => true, 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['status'])
            ->addIndex(['external_id'])
            ->create();
    }
}
