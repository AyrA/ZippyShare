# ZippyShare

Synology ZippyShare Plugin

This Plugin allows you to download files from ZippyShare using your Synology NAS Downloader

## Features

- Very simple and minimalistic code
- Catches most types of errors (File not found, invalid URL, new unsolvable challenge, etc)
- Regular Expression matching that is permissive enough to allow for slight Changes in the Future
- Doesn't depends on Document Title anymore
- Solves computational Challenge without using `eval`

## Download

Check [the Releases Section](https://github.com/AyrA/ZippyShare/releases) for the latest Version of `zippy.host`

## Installation

1. Open your Synology Website
2. Go to the Download Station
3. Open the Settings
4. Click on "File Hosting"
5. Click "Add"
6. Select `zippy.host` from your Computer

## Updating

1. Open your Synology Website
2. Go to the Download Station
3. Open the Settings
4. Click on "File Hosting"
5. Search for the Zippyshare plugin
6. Click "Delete" and confirm action
7. Select `zippy.host` from your Computer

## Testing

As of now, the download script will be placed in `/usr/syno/etc/packages/DownloadStation/download/userhosts/zippyshare`

1. Enable SSH on your NAS
2. Connect via SSH to your NAS
3. navigate to `/usr/syno/etc/packages/DownloadStation/download/userhosts/zippyshare`

### Getting Information

Run `php zippy.php test https://...` (supply any valid ZippyShare URL)

### Downloading File

Run `php zippy.php get https://...` (supply any valid ZippyShare URL)

## Building from source

You can either build it manually or automatically

### Automatic Building

1. Download and install the latest 64 bit Version of [7-Zip](https://7-zip.org/)
2. Run `build.bat`

### Manual Building

1. Compress `INFO` and `zippy.php` into a tar archive `zippy.tar`
2. Compress `zippy.tar` into the gzip archive `zippy.tar.gz`
3. Rename `zippy.tar.gz` to `zippy.host`
4. Delete `zippy.tar`
