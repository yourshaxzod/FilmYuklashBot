<?php

namespace App\Services;

use SergiX44\Nutgram\Nutgram;
use App\Models\{Video, Movie};
use App\Helpers\{Formatter, Keyboard};
use PDO;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Input\InputMediaVideo;

class VideoService
{
    public static function handlePlay(Nutgram $bot, PDO $db, int $id): void
    {
        $video = Video::find($db, $id);
        if (!$video) {
            $bot->answerCallbackQuery(text: "⚠️ Video topilmadi", show_alert: true);
            return;
        }

        $caption = $bot->message()->caption;
        $bot->editMessageMedia(
            media: InputMediaVideo::make(
                media: $video['video_file_id'],
                caption: $caption,
                parse_mode: ParseMode::HTML,
            ),
            reply_markup: Keyboard::videoBackButton($video['movie_id'])
        );
    }

    public static function handleAddFile(Nutgram $bot, PDO $db): void
    {
        $video = $bot->message()->video;
        $fileId = $video->file_id;
        $videoData = $bot->getUserData('video_data');

        try {
            $db->beginTransaction();
            Video::create($db, [
                'movie_id' => $videoData['movie_id'],
                'title' => $videoData['title'],
                'video_file_id' => $fileId,
                'part_number' => $videoData['part_number']
            ]);
            $db->commit();

            $bot->sendMessage(
                text: Formatter::videoAddSuccess($videoData),
                reply_markup: Keyboard::videoAddActions($videoData['movie_id'])
            );

            $bot->setUserData('state', null);
            $bot->setUserData('video_data', null);
        } catch (Exception $e) {
            $db->rollBack();
            $bot->sendMessage("⚠️ Xatolik yuz berdi. Qayta urinib ko'ring.");
        }
    }

    // ... other video related methods
}
