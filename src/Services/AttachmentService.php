<?php

namespace mradang\LaravelAttachment\Services;

use mradang\LaravelAttachment\Models\Attachment;
use mradang\LaravelAttachment\Jobs\MakeThumbnail;

class AttachmentService
{

    public static function createByFile($class, $key, $file, $data)
    {
        $filename = FileService::uploadFile($file);

        if (!$filename) {
            throw new \Exception('上传文件失败！');
        }

        return self::create($class, $key, $filename, $data);
    }

    public static function createByUrl($class, $key, $url, $data)
    {
        $filename = FileService::uploadUrl($url);

        if (!$filename) {
            throw new \Exception('获取远程文件失败！');
        }

        return self::create($class, $key, $filename, $data);
    }

    private static function create($class, $key, $filename, $data)
    {
        $imagesize = @getimagesize(storage_path($filename));

        $attachment = new Attachment([
            'attachmentable_type' => $class,
            'attachmentable_id' => $key,
            'filename' => $filename,
            'filesize' => filesize(storage_path($filename)),
            'imageInfo' => is_array($imagesize) ? ['width' => $imagesize[0], 'height' => $imagesize[1]] : null,
            'sort' => Attachment::where([
                'attachmentable_id' => $key,
                'attachmentable_type' => $class,
            ])->max('sort') + 1,
            'data' => $data,
        ]);

        if ($attachment->save()) {
            return $attachment;
        }
    }

    public static function deleteFile($class, $key, $id)
    {
        $attachment = Attachment::findOrFail($id);
        if ($attachment->attachmentable_id === $key && $attachment->attachmentable_type === $class) {
            if (FileService::deleteFile($attachment->filename)) {
                $attachment->delete();
            }
        }
    }

    public static function clear($class, $key)
    {
        $attachments = Attachment::where([
            'attachmentable_id' => $key,
            'attachmentable_type' => $class,
        ])->get();
        foreach ($attachments as $attachment) {
            if (FileService::deleteFile($attachment->filename)) {
                $attachment->delete();
            }
        }
    }

    public static function download($class, $key, $id)
    {
        $attachment = Attachment::findOrFail($id);
        if ($attachment->attachmentable_id === $key && $attachment->attachmentable_type === $class) {
            return response()->download(storage_path($attachment->filename));
        }
    }

    public static function showImage($class, $key, $id, $width, $height)
    {
        $attachment = Attachment::where([
            'id' => $id,
            'attachmentable_id' => $key,
            'attachmentable_type' => $class,
        ])->firstOrFail();

        if (empty($attachment->imageInfo)) {
            return response('非图片', 400);
        }

        $filename = $attachment->filename;

        if ($width && $height) {
            $thumb = FileService::generateThumbName($filename, $width, $height);
            if (is_file(storage_path($thumb))) {
                $filename = $thumb;
            } else {
                dispatch(new MakeThumbnail($filename, $width, $height));
            }
        }

        return response()->file(storage_path($filename));
    }

    public static function find($class, $key, $id)
    {
        return Attachment::where([
            'id' => $id,
            'attachmentable_id' => $key,
            'attachmentable_type' => $class,
        ])->first();
    }

    public static function saveSort($class, $key, array $data)
    {
        foreach ($data as $item) {
            Attachment::where([
                'id' => $item['id'],
                'attachmentable_id' => $key,
                'attachmentable_type' => $class,
            ])->update(['sort' => $item['sort']]);
        }
    }
}
