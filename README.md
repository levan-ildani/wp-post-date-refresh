# WP Post Date Refresh

WP Post Date Refresh is a lightweight WordPress plugin that adds a simple date refresh button to the post and page editor.

It lets editors set the publish date and modified date to the current WordPress site time, or to the current site time minus a chosen number of hours.

## Features

- Works with posts and pages.
- Adds a small editor meta box.
- Supports Classic Editor.
- Supports the Gutenberg/block editor meta box area.
- Updates `post_date`, `post_date_gmt`, `post_modified`, and `post_modified_gmt`.
- Uses WordPress site timezone functions.
- Uses AJAX, nonce verification, and per-post edit capability checks.
- No settings page and no external libraries.

## Requirements

- WordPress 6.x or newer.
- PHP 7.4 or newer.

## Installation

1. Download or clone this repository.
2. Copy the `wp-post-date-refresh` folder into `wp-content/plugins/`.
3. In WordPress admin, go to **Plugins**.
4. Activate **WP Post Date Refresh**.

## Usage

1. Open any post or page in the WordPress editor.
2. Find the **Quick Date Refresh** meta box.
3. Enter an **Hours offset**:
   - Use `0` to set the date to the current WordPress site time.
   - Use a positive number, such as `3`, to set the date to now minus 3 hours.
4. Click **Set date**.

After a successful update, the plugin shows: `Date updated successfully.`

## Security

The plugin only runs in the WordPress admin area. Date updates require:

- A valid WordPress AJAX nonce.
- A logged-in user.
- Permission to edit the current post or page.

The hours offset input is sanitized, validated, and limited to non-negative whole numbers.

## License

MIT License. Free to use, copy, modify, and distribute.
