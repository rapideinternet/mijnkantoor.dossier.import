<?php namespace SourceFilesystem;

enum FileSystemTypeEnum: string {
    case LOCAL = 'local';
    case MIJNKANTOOR = 'mijnkantoor';
    case SHAREPOINT = 'sharepoint';
}