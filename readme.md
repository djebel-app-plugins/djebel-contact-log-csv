# Djebel Contact Log CSV

Logs djebel-contact form submissions to dated CSV files in the site's private
data dir.

A small listener plugin: it subscribes to the contact plugin's
`app.plugin.contact.message_processed` action and appends each submission to a
CSV file. The contact plugin stays focused on handling the form; this plugin
owns the storage concern — the two talk only through the public hook.

## Where the data goes

Files are written OUTSIDE the web root, under this plugin's private data dir:

```
.ht_djebel/data/app/plugins/djebel-contact-log-csv/{YYYY}/{MM}/data_{YYYY}-{MM}-{DD}.csv
```

One file per day, organized into year/month folders. When a new file starts, a
header row is written automatically — the columns mirror the submission fields
carried by the hook context.

## Features

- Zero configuration — install it and every submission is logged
- One dated CSV per day, organized in `{YYYY}/{MM}` folders
- Header row auto-generated from the submission fields
- Each write happens under an exclusive lock (`LOCK_EX`) — concurrent
  submissions never corrupt a row
- Private storage — the CSV files are never web-accessible
- A failed write goes to `error_log()` and never breaks the visitor's form
  submission

## Requirements

- PHP 5.6+
- The djebel-contact plugin — it fires the
  `app.plugin.contact.message_processed` action this plugin listens on

## Install

Add it to a site as a git submodule in the private plugins dir:

```bash
git submodule add https://github.com/djebel-app-plugins/djebel-contact-log-csv.git .ht_djebel/app/plugins/djebel-contact-log-csv
```

Djebel auto-loads plugins from that dir — no registration needed.

## Filters

| Filter | Purpose |
|---|---|
| `app.plugin.contact_log_csv.file` | Override the CSV file the submission is written to |

## License

GPLv2 or later. Author: Svetoslav Marinov (Slavi), [Orbisius](https://orbisius.com)
