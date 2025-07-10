<?php namespace SourceFilesystem;

class FilesystemFactory {
    public static function create(FileSystemTypeEnum $type, array $config): FilesystemContract {
        return match ($type) {
            FileSystemTypeEnum::LOCAL => new Local($config),
            FileSystemTypeEnum::MIJNKANTOOR => new MijnKantoor($config),
            FileSystemTypeEnum::SHAREPOINT => new SharePoint($config),
        };
    }
}