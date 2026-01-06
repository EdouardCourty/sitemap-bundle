# Test Application

This is a minimal Symfony application used for functional testing of the SitemapBundle.

## Structure

```
tests/
├── App/
│   ├── Kernel.php              # Test kernel
│   └── Controller/
│       └── TestController.php  # Simple controller
├── app/
│   ├── config/
│   │   ├── routes.yaml         # Test routes
│   │   └── sitemap.yaml        # Bundle configuration
│   ├── public/
│   │   └── index.php           # Front controller
│   ├── .env                    # Environment variables
│   └── README.md               # This file
├── Fixtures/
│   └── Entity/
│       ├── Song.php            # Test entity
│       └── Article.php         # Test entity
└── Functional/
    └── SitemapGenerationTest.php  # Functional tests
```

## Running the Test Server

From the project root:

```bash
# Start PHP built-in server
php -S localhost:8000 -t tests/app/public

# Then visit:
# http://localhost:8000/          # Home page
# http://localhost:8000/about     # About page
# http://localhost:8000/contact   # Contact page
# http://localhost:8000/song/123  # Song page with UID
# http://localhost:8000/article/my-article  # Article page with slug
```

Note: The test app uses in-memory SQLite, so there's no persistent data. For functional tests, data is inserted in setUp().

## Testing

Run functional tests:

```bash
# All functional tests
vendor/bin/phpunit tests/Functional/

# Specific test
vendor/bin/phpunit tests/Functional/SitemapGenerationTest.php
```

## Configuration

The test app is configured with:
- SQLite in-memory database
- Two test entities (Song, Article)
- Static routes (home, about, contact)
- Entity routes (song_show, article_show)
- Sitemap configuration in `tests/app/config/sitemap.yaml`
- Simple HTML responses (no Twig required)
