<?php

use yii\db\Migration;

class m250617_042805_test_connection extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m250617_042805_test_connection cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m250617_042805_test_connection cannot be reverted.\n";

        return false;
    }
    */
}
