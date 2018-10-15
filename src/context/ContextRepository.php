<?php

namespace yandex\alisa\context;

use Lazer\Classes\Database as DB;

class ContextRepository
{
    const TABLE_NAME = 'context';

    public function __construct()
    {
        try {
            \Lazer\Classes\Helpers\Validate::table(self::TABLE_NAME)->exists();
        } catch (\Lazer\Classes\LazerException $e) {
            DB::create(self::TABLE_NAME, array(
                'user_id' => 'string',
                'session_id' => 'string',
                'message_id' => 'integer',
                'context' => 'string',
                'date' => 'string',
            ));
        }
    }

    /**
     * @param string $userId
     * @param string $sessionId
     *
     * @return bool
     */
    public function exists($userId, $sessionId)
    {
        $row = DB::table(self::TABLE_NAME)->where('user_id', '=', $userId)->where('session_id', '=', $sessionId)->find();
        return ($row && $row->count()) ? true : false;
    }

    /**
     * @param string $userId
     * @param string $sessionId
     *
     * @return Lazer\Classes\Database|bool
     */
    public function get($userId, $sessionId)
    {
        $row = DB::table(self::TABLE_NAME)->where('user_id', '=', $userId)->where('session_id', '=', $sessionId)->find();
        return ($row && $row->count()) ? $row : false;
    }

    /**
     * @param string $userId
     * @param string $sessionId
     */
    public function remove($userId, $sessionId)
    {
        DB::table(self::TABLE_NAME)->where('user_id', '=', $userId)->where('session_id', '=', $sessionId)->limit(1)->find()->delete();
    }

    /**
     * @param string $userId
     * @param string $sessionId
     * @param array $context
     */
    public function update($userId, $sessionId, $context)
    {
        $row = DB::table(self::TABLE_NAME)->where('user_id', '=', $userId)->where('session_id', '=', $sessionId)->find();
        if(!$row || !$row->count()){
            $row = DB::table(self::TABLE_NAME);
            $row->user_id = $userId;
            $row->session_id = $sessionId;
        }
        $row->context = json_encode($context, JSON_UNESCAPED_UNICODE);
        $row->date = date(DATE_ATOM);
        $row->save();
    }
}