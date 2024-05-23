# MijnKantoor Directory and File Management Script

This PHP script automates the process of creating directories and uploading files to MijnKantoor, utilizing the
MijnKantoor API. It is designed to efficiently handle customer directories, check for the presence of files and
directories, and maintain a cache to optimize performance.

## Prerequisites

- PHP 7.4 or later
- cURL extension for PHP
- An `.env` file with the necessary configuration variables (see `.env.example`)

## Installation

1. **Clone the repository**:

    ```sh
    git clone <repository-url>
    cd <repository-directory>
    ```

2. **Create an `.env` file**:

   Copy the provided `.env.example` to `.env` and fill in your specific configuration details.

    ```sh
    cp .env.example_root .env
    ```

3. **Set up the `.env` file** with your specific configuration details.

## Configuration

### .env File

- `BEARER_TOKEN`: Your MijnKantoor API bearer token.
- `TENANT`: Your MijnKantoor tenant identifier.
- `BASE_URL`: The base URL for the MijnKantoor API.
- `ROOT_DIR`: The root directory ID where all directories will be created.
- `DRY_RUN`: Set to `true` to simulate API interactions without making actual changes.
- `CUSTOMER_FOLDER_PATH`: The path to the customer folders on the local machine.

**Note**: The `ROOT_DIR` should have `allow_non_global_children` set to `true` to allow the creation of non-global
directories.

### Example .env File

```env
BEARER_TOKEN=your_bearer_token
TENANT=jab9vr74pkr46e87
BASE_URL=https://v2.api.mijnkantoorapp.nl/v1
ROOT_DIR=vxakb5kmx6woqy9p
DRY_RUN=false
```

## Purpose

The purpose of this script is to automate the import of a hierarchical directory structure and files into MijnKantoor.
The script will create directories on the remote server and upload files as specified in the local directory structure.
When the script encounters directories that contain both files and subdirectories, it will create a directory called
'overig' and upload the loose files to that directory because of the limitation that MijnKantoor only allows files
to be placed in side directories that have the is_leaf flag set to true.

## Usage

1. **Run the script**:

    ```sh
    php import.php
    ```

   The script will process customer directories, create necessary directories in MijnKantoor, and upload files as
   specified.

## Notes

- Ensure the root directory (`ROOT_DIR`) has `allow_non_global_children` set to `true` to allow the creation of
  non-global directories.
- This script only works with S3 storage and all items are created as S3 files.