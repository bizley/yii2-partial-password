<?php

/**
 * Partial password implementation for Yii 2.
 * @author Paweł Bizley Brzozowski
 * @version 1.0
 * @license http://opensource.org/licenses/Apache-2.0
 * 
 * https://github.com/bizley-code/yii2-partial-password
 * 
 * Creates password table.
 */
class m150913_180508_create_password_table extends \yii\db\Migration
{
    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            $tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('password', [
            'id'            => $this->primaryKey(),
            'user_id'       => $this->integer()->notNull(),
            'pattern'       => $this->integer()->notNull(),
            'password_hash' => $this->string(255)->notNull(),
        ], $tableOptions);
        
        $this->addForeignKey('fk-password-user_id', 'password', 'user_id', 'user', 'id', 'CASCADE', 'CASCADE');
    }

    public function down()
    {
        $this->dropTable('password');
    }
}
