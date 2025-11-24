# AI Content Strategist

A WordPress plugin that exposes Jetpack Stats data and content audit capabilities via the WordPress Abilities API, making them discoverable and callable by AI assistants through the Model Context Protocol (MCP).

## Overview

This plugin bridges the gap between site analytics and content databases, enabling AI to synthesize both data sources to provide strategic content recommendations:

- What content to write next
- What existing content to revive or update
- What underperforming content to consider removing

## Requirements

- WordPress 6.5+ (or WordPress with Abilities API installed)
- PHP 8.0+
- Jetpack (with active WordPress.com connection for Stats features)
- [WordPress Abilities API](https://github.com/WordPress/abilities-api)
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter)

## Installation

### Via Composer (Recommended)

```bash
cd wp-content/plugins/ai-content-strategist
composer install
```

### Manual Installation

1. Clone or download this plugin to `wp-content/plugins/ai-content-strategist`
2. Install the required dependencies via Composer
3. Activate the plugin in WordPress admin

## Dependencies

This plugin requires the following Composer packages:

- `wordpress/abilities-api` - Provides the ability registration system
- `wordpress/mcp-adapter` - Bridges abilities to the MCP protocol

## Registered Abilities

### 1. `content-strategist/get-top-posts`

Returns the site's top performing posts by views.

**Input:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days` | integer | 30 | Period to analyze (7, 30, or 90) |
| `limit` | integer | 10 | Maximum results (1-50) |

**Output:** Array of objects with:
- `post_id` - WordPress post ID
- `title` - Post title
- `url` - Permalink
- `views` - Total views in period
- `date_published` - ISO 8601 date
- `categories` - Array of category names

**Requires:** Jetpack connection

---

### 2. `content-strategist/get-search-terms`

Returns search terms people used to find the site.

**Input:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days` | integer | 30 | Period to analyze |
| `limit` | integer | 20 | Maximum results (1-100) |

**Output:** Array of objects with:
- `term` - The search term
- `count` - Number of searches

**Requires:** Jetpack connection

---

### 3. `content-strategist/get-stale-drafts`

Finds draft posts that have been sitting unfinished.

**Input:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days_old` | integer | 180 | Find drafts older than this |
| `limit` | integer | 20 | Maximum results (1-50) |

**Output:** Array of objects with:
- `post_id` - WordPress post ID
- `title` - Draft title (or "Untitled")
- `excerpt` - First 150 characters
- `date_created` - ISO 8601 date
- `date_modified` - ISO 8601 date
- `days_since_modified` - Days since last edit
- `categories` - Array of category names
- `word_count` - Approximate word count

**Requires:** WordPress only (no Jetpack needed)

---

### 4. `content-strategist/get-underperforming-posts`

Finds published posts with low traffic.

**Input:**
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `days_published` | integer | 90 | Only posts older than this |
| `max_views` | integer | 100 | Views threshold |
| `limit` | integer | 20 | Maximum results (1-50) |

**Output:** Array of objects with:
- `post_id` - WordPress post ID
- `title` - Post title
- `url` - Permalink
- `date_published` - ISO 8601 date
- `views` - Total views
- `categories` - Array of category names
- `word_count` - Approximate word count

**Requires:** Jetpack connection

## Testing

### REST API

List all registered abilities:

```bash
curl -X GET "https://your-site.com/wp-json/wp-abilities/v1/abilities" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Execute an ability:

```bash
curl -X POST "https://your-site.com/wp-json/wp-abilities/v1/content-strategist/get-stale-drafts/run" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"days_old": 90, "limit": 5}'
```

### MCP Inspector

Use [Anthropic's MCP Inspector](https://github.com/anthropics/mcp-inspector) to verify abilities are exposed correctly via MCP.

### Claude Desktop

Configure Claude Desktop with `mcp-wordpress-remote` pointing to your site to test AI interactions.

## Example AI Workflow

Here's how an AI assistant might use these abilities:

1. **Discovery**: "What can this WordPress site do?"
   - AI discovers the content-strategist abilities

2. **Analysis**: "Show me top performing content"
   - Calls `get-top-posts` with `days: 30, limit: 10`

3. **Opportunity Finding**: "What are people searching for?"
   - Calls `get-search-terms` with `days: 30, limit: 20`

4. **Content Audit**: "What drafts have I forgotten about?"
   - Calls `get-stale-drafts` with `days_old: 180`

5. **Performance Review**: "What published posts aren't getting traffic?"
   - Calls `get-underperforming-posts` with `max_views: 100`

6. **Strategy Synthesis**: "Based on all this, give me a content strategy"
   - AI combines all data to provide strategic recommendations

## Caching

Stats-related abilities implement 15-minute transient caching to reduce API calls to WordPress.com. Cache keys are based on the ability parameters.

## Permissions

All abilities require the `edit_posts` capability. This ensures only authenticated users with content editing permissions can access the data.

## Error Handling

When Jetpack is not connected, stats abilities return a `WP_Error` with:
- Code: `jetpack_not_connected`
- Status: 503 (Service Unavailable)
- Message: Instructions to connect Jetpack

## Hooks

The plugin hooks into:
- `wp_abilities_api_init` - Register abilities
- `plugins_loaded` - Initialize plugin
- `admin_notices` - Show Jetpack connection warnings

## File Structure

```
ai-content-strategist/
├── ai-content-strategist.php    # Main plugin file
├── composer.json                 # Dependencies
├── includes/
│   ├── class-plugin.php         # Main plugin class
│   ├── class-stats-abilities.php    # Jetpack Stats abilities
│   └── class-content-abilities.php  # Content audit abilities
└── README.md                     # This file
```

## License

GPL-2.0-or-later

## Contributing

Contributions are welcome! Please ensure code follows WordPress coding standards and includes appropriate documentation.
