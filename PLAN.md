# ecourty/sitemap-bundle - Plan de développement

## Vue d'ensemble
Bundle Symfony permettant de générer des sitemaps XML conformes au protocole sitemap.org, avec support des sitemaps index pour les gros volumes (> 50 000 URLs).

### Objectifs
- ✅ Générer des sitemaps XML valides (norme sitemap.org)
- ✅ Controller pour servir le sitemap à la volée (`/sitemap.xml`)
- ✅ Command pour générer le sitemap statiquement (`public/sitemap.xml`)
- ✅ Support des routes statiques (sans paramètres)
- ✅ Support des routes dynamiques (avec entités Doctrine)
- ✅ Support des sitemaps index (un fichier par source + index)
- ✅ Configuration flexible via YAML
- ✅ Architecture simple et efficace

### Contraintes techniques
- PHP 8.3+
- Symfony 6.4+ / 7.0+ / 8.0+
- Doctrine ORM 3.0+ / 4.0+
- Extension native `ext-xmlwriter`
- PHPStan Level 9
- PSR-12 code style
- Utilisation des attributs PHP 8 (pas de YAML/XML pour routes, commandes)

---

## Architecture

### 1. Structure des répertoires
```
src/
├── Contract/
│   ├── UrlProviderInterface.php       # Interface pour les providers d'URLs
│   └── SitemapGeneratorInterface.php  # Interface du générateur principal
├── Controller/
│   └── SitemapController.php          # Servir le sitemap à la volée
├── Command/
│   └── DumpSitemapCommand.php         # Générer le sitemap statique
├── Enum/
│   └── ChangeFrequency.php            # Enum pour changefreq (always, hourly, daily, weekly, monthly, yearly, never)
├── Model/
│   ├── SitemapUrl.php                 # DTO pour une URL de sitemap
│   ├── StaticRouteConfig.php          # DTO pour config route statique
│   └── EntityRouteConfig.php          # DTO pour config route dynamique
├── Service/
│   ├── SitemapGenerator.php           # Service principal de génération
│   ├── XmlWriter.php                  # Service d'écriture XML (sitemap simple)
│   ├── SitemapIndexWriter.php         # Service d'écriture XML (sitemap index)
│   └── UrlProviderRegistry.php        # Registry des providers d'URLs
├── Provider/
│   ├── StaticRouteUrlProvider.php     # Provider pour routes statiques
│   └── EntityRouteUrlProvider.php     # Provider pour routes dynamiques avec entités
├── Exception/
│   ├── SitemapException.php           # Exception de base
│   ├── InvalidConfigurationException.php
│   └── FileWriteException.php
├── DependencyInjection/
│   ├── Configuration.php              # Définition de la config
│   └── EcourtySitemapExtension.php    # Extension du bundle
├── Resources/
│   └── config/
│       └── services.yaml              # Configuration des services
└── SitemapBundle.php                  # Classe principale du bundle
```

---

## 2. Configuration YAML

### Format proposé
```yaml
sitemap:
    # Base URL du site (pour générer les URLs absolues)
    base_url: 'https://example.com'
    
    # Stratégie de sitemap index
    # 'auto' : génère un index si total URLs > index_threshold (défaut)
    # true : toujours générer un index (même avec peu d'URLs)
    # false : jamais d'index, un seul fichier sitemap.xml
    use_index: 'auto'
    index_threshold: 50000  # Seuil pour déclencher l'index en mode 'auto'
    
    # Routes statiques (sans paramètres)
    static_routes:
        - route: 'homepage'
          priority: 1.0
          changefreq: 'daily'
          lastmod: '-2 days'  # Relative time string (optionnel)
        
        - route: 'blog_list'
          priority: 0.9
          changefreq: 'weekly'
          # lastmod non défini = pas de tag <lastmod>
    
    # Routes dynamiques (avec entités Doctrine)
    entity_routes:
        - entity: 'App\Entity\Song'
          route: 'song_show'
          route_params:
              uid: 'uid'  # propriété entité -> paramètre route
          priority: 0.8
          changefreq: 'weekly'
          lastmod_property: 'updatedAt'  # Propriété DateTime de l'entité (optionnel)
          query_builder_method: 'findAllForSitemap'  # Méthode du repository (optionnel)
          # Si query_builder_method non défini, utilise findAll()
        
        - entity: 'App\Entity\Post'
          route: 'post_show'
          route_params:
              slug: 'slug'
          priority: 0.7
          changefreq: 'monthly'
          conditions: 'e.published = true'  # DQL WHERE clause (optionnel)
          # Alternatives : query_builder_method OU conditions (pas les deux)
```

### Validation de config
- `base_url` obligatoire
- `use_index` : 'auto', true, ou false (défaut : 'auto')
- `index_threshold` : entier positif (défaut : 50000)
- `route` doit exister dans le routing Symfony
- `entity` doit être une classe Doctrine valide
- `route_params` : les propriétés doivent exister sur l'entité
- `lastmod_property` doit être une propriété DateTime/DateTimeImmutable
- `changefreq` doit être une valeur valide de l'enum `ChangeFrequency`
- `priority` entre 0.0 et 1.0
- Soit `query_builder_method`, soit `conditions`, pas les deux

---

## 3. Modèles de données

### `SitemapUrl` (DTO)
```php
readonly class SitemapUrl
{
    public function __construct(
        public string $loc,
        public float $priority,
        public ChangeFrequency $changefreq,
        public ?\DateTimeInterface $lastmod = null,
    ) {}
}
```

### `StaticRouteConfig` (DTO)
```php
readonly class StaticRouteConfig
{
    public function __construct(
        public string $route,
        public float $priority,
        public ChangeFrequency $changefreq,
        public ?string $lastmodRelative = null, // ex: '-2 days'
    ) {}
}
```

### `EntityRouteConfig` (DTO)
```php
readonly class EntityRouteConfig
{
    public function __construct(
        public string $entity,
        public string $route,
        public array $routeParams, // ['slug' => 'slug']
        public float $priority,
        public ChangeFrequency $changefreq,
        public ?string $lastmodProperty = null,
        public ?string $queryBuilderMethod = null,
        public ?string $conditions = null,
    ) {}
}
```

---

## 4. Services principaux

### `SitemapGenerator`
**Responsabilité** : Orchestrer la génération complète du sitemap

**Méthodes publiques** :
```php
interface SitemapGeneratorInterface
{
    // Génère et retourne le XML en string (pour Controller)
    public function generate(): string;
    
    // Génère et écrit dans un fichier (pour Command)
    public function generateToFile(string $path, bool $force = false): void;
    
    // Compte le nombre total d'URLs (pour décider index ou pas)
    public function countUrls(): int;
}
```

**Logique** :
1. Récupère toutes les URLs via `UrlProviderRegistry` (groupées par source)
2. Décide de la stratégie selon `use_index` :
   - `'auto'` : index si `count > index_threshold`
   - `true` : toujours index
   - `false` : jamais index (un seul fichier)
3. Génère le sitemap selon la stratégie choisie

### `XmlWriter`
**Responsabilité** : Écrire un sitemap XML simple

```php
class XmlWriter
{
    public function write(array $urls): string; // Retourne XML string
    public function writeToFile(array $urls, string $path): void;
}
```

**Format XML généré** :
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>https://example.com/page</loc>
        <lastmod>2026-01-05</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
</urlset>
```

### `SitemapIndexWriter`
**Responsabilité** : Écrire un sitemap index + plusieurs sitemaps

```php
class SitemapIndexWriter
{
    // Génère un index + plusieurs fichiers sitemap
    // Retourne un tableau : ['index' => string, 'sitemaps' => ['static.xml' => string, 'entity_song.xml' => string]]
    public function write(array $urlsBySource): array;
    
    // Écrit l'index + les sitemaps dans le dossier
    public function writeToFile(array $urlsBySource, string $basePath): void;
}
```

**Format index** :
```xml
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <sitemap>
        <loc>https://example.com/sitemap_static.xml</loc>
        <lastmod>2026-01-05T20:30:00+00:00</lastmod>
    </sitemap>
    <sitemap>
        <loc>https://example.com/sitemap_entity_song.xml</loc>
        <lastmod>2026-01-05T20:30:00+00:00</lastmod>
    </sitemap>
</sitemapindex>
```

**Stratégie de split** :
- Un sitemap par "source" : `sitemap_static.xml`, `sitemap_entity_song.xml`, `sitemap_entity_post.xml`
- Chaque fichier ≤ 50 000 URLs (norme sitemap.org)
- Si une source a > 50k URLs, la splitter en chunks (`sitemap_entity_song_1.xml`, `sitemap_entity_song_2.xml`)

**Algorithme de splitting** :
```php
public function writeToFile(array $urlsBySource, string $basePath): void
{
    $baseDir = dirname($basePath);
    $indexPath = $basePath; // Ex: /public/sitemap.xml
    
    $sitemapFiles = [];
    
    foreach ($urlsBySource as $sourceName => $urls) {
        $urlCount = count($urls);
        
        if ($urlCount <= 50000) {
            // Une seule source, un seul fichier
            $filename = sprintf('sitemap_%s.xml', $sourceName);
            $filepath = $baseDir . '/' . $filename;
            
            $this->xmlWriter->writeToFile($urls, $filepath);
            $sitemapFiles[] = [
                'loc' => $this->baseUrl . '/' . $filename,
                'lastmod' => new \DateTime(),
            ];
        } else {
            // Split en chunks de 50k
            $chunks = array_chunk($urls, 50000);
            
            foreach ($chunks as $index => $chunk) {
                $chunkNumber = $index + 1;
                $filename = sprintf('sitemap_%s_%d.xml', $sourceName, $chunkNumber);
                $filepath = $baseDir . '/' . $filename;
                
                $this->xmlWriter->writeToFile($chunk, $filepath);
                $sitemapFiles[] = [
                    'loc' => $this->baseUrl . '/' . $filename,
                    'lastmod' => new \DateTime(),
                ];
            }
        }
    }
    
    // Écrire l'index
    $this->writeIndexFile($sitemapFiles, $indexPath);
}

private function writeIndexFile(array $sitemapFiles, string $path): void
{
    $xml = new \XMLWriter();
    $xml->openMemory();
    $xml->startDocument('1.0', 'UTF-8');
    $xml->setIndent(true);
    
    $xml->startElement('sitemapindex');
    $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
    
    foreach ($sitemapFiles as $sitemap) {
        $xml->startElement('sitemap');
        
        $xml->startElement('loc');
        $xml->text($sitemap['loc']);
        $xml->endElement();
        
        $xml->startElement('lastmod');
        $xml->text($sitemap['lastmod']->format('c')); // ISO 8601
        $xml->endElement();
        
        $xml->endElement(); // sitemap
    }
    
    $xml->endElement(); // sitemapindex
    $xml->endDocument();
    
    file_put_contents($path, $xml->outputMemory());
}
```

**Exemple concret** :
- Source `static` : 100 URLs → `sitemap_static.xml`
- Source `entity_song` : 75 000 URLs → `sitemap_entity_song_1.xml` (50k) + `sitemap_entity_song_2.xml` (25k)
- Source `entity_post` : 30 000 URLs → `sitemap_entity_post.xml`
- Index : `sitemap.xml` référence les 4 fichiers ci-dessus

### `UrlProviderRegistry`
**Responsabilité** : Gérer les providers d'URLs (pattern Registry)

```php
class UrlProviderRegistry
{
    public function __construct(iterable $providers) {} // !tagged_iterator
    
    // Retourne toutes les URLs groupées par source
    public function getAllUrlsBySource(): array; // ['static' => [SitemapUrl, ...], 'entity_song' => [...]]
    
    // Retourne toutes les URLs à plat
    public function getAllUrls(): array; // [SitemapUrl, ...]
    
    // Compte total d'URLs
    public function count(): int;
}
```

---

## 5. Providers d'URLs

### `UrlProviderInterface`
```php
interface UrlProviderInterface
{
    public function getUrls(): iterable; // Retourne des SitemapUrl
    public function getSourceName(): string; // Ex: 'static', 'entity_song'
}
```

### `StaticRouteUrlProvider`
**Responsabilité** : Générer les URLs pour les routes statiques

**Configuration injectée** : `array<StaticRouteConfig>`

**Logique** :
1. Pour chaque `StaticRouteConfig`
2. Générer l'URL absolue via `UrlGeneratorInterface::generate($route, [], ABSOLUTE_URL)`
3. Calculer `lastmod` si `lastmodRelative` défini (via `new DateTime($lastmodRelative)`)
4. Créer un `SitemapUrl`

### `EntityRouteUrlProvider`
**Responsabilité** : Générer les URLs pour les routes dynamiques avec entités

**Configuration injectée** : `array<EntityRouteConfig>`

**Dépendances** :
- `ManagerRegistry` (Doctrine)
- `UrlGeneratorInterface`
- `PropertyAccessorInterface` (Symfony PropertyAccess)

**Logique** :
1. Pour chaque `EntityRouteConfig`
2. Récupérer le repository de l'entité
3. Si `queryBuilderMethod` défini → appeler cette méthode
4. Sinon si `conditions` défini → créer un QueryBuilder avec `WHERE $conditions`
5. Sinon → `findAll()`
6. Itérer sur les entités :
   - Extraire les paramètres de route via PropertyAccessor (`$entity->getSlug()` etc.)
   - Générer l'URL absolue
   - Extraire `lastmod` via `lastmodProperty` si défini
   - Créer un `SitemapUrl`

**Optimisation** : Utiliser `toIterable()` de Doctrine pour streamer les entités (éviter de charger 100k entités en mémoire)

---

## 6. Controller

### `SitemapController`
```php
#[Route('/sitemap.xml', name: 'ecourty_sitemap.index', methods: ['GET'])]
public function index(SitemapGeneratorInterface $generator): Response
{
    $xml = $generator->generate();
    
    return new Response($xml, 200, [
        'Content-Type' => 'application/xml; charset=UTF-8',
    ]);
}
```

**Note** : Si `use_index = true` et seuil dépassé, retourne le sitemap index.

**Optimisation future** : Ajouter du cache HTTP (`Cache-Control`, `ETag`)

---

## 7. Command

### `DumpSitemapCommand`
```php
#[AsCommand(
    name: 'sitemap:dump',
    description: 'Generate sitemap XML file(s)'
)]
class DumpSitemapCommand extends Command
{
    private const DEFAULT_OUTPUT_PATH = '/sitemap.xml'; // Relatif au public dir
    
    public function __construct(
        private readonly SitemapGeneratorInterface $generator,
        private readonly string $publicDir,
    ) {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output path (absolute or relative to public dir)')
             ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing file');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputPath = $input->getOption('output');
        
        // Si pas de --output, utiliser publicDir + DEFAULT_OUTPUT_PATH
        if ($outputPath === null) {
            $path = $this->publicDir . self::DEFAULT_OUTPUT_PATH;
        } elseif (!str_starts_with($outputPath, '/')) {
            // Chemin relatif, préfixer avec publicDir
            $path = $this->publicDir . '/' . $outputPath;
        } else {
            // Chemin absolu
            $path = $outputPath;
        }
        
        $force = $input->getOption('force');
        
        if (file_exists($path) && !$force) {
            $io->warning("File already exists: $path");
            if (!$io->confirm('Overwrite?', false)) {
                return Command::FAILURE;
            }
        }
        
        $startTime = microtime(true);
        $this->generator->generateToFile($path, force: true);
        $duration = microtime(true) - $startTime;
        
        $io->success(sprintf('Sitemap generated in %.2f seconds: %s', $duration, $path));
        
        return Command::SUCCESS;
    }
}
```

**Features** :
- Nom court : `sitemap:dump`
- Path par défaut : `%kernel.project_dir%/public/sitemap.xml`
- Option `--output` pour override (absolu ou relatif au public dir)
- Option `--force` pour écraser sans confirmation
- Confirmation interactive si le fichier existe
- Affichage du temps de génération

---

## 8. Exceptions

### Hiérarchie
```
SitemapException (base)
├── InvalidConfigurationException
│   ├── RouteNotFoundException
│   ├── EntityNotFoundException
│   └── InvalidPropertyException
└── FileWriteException
```

**Messages clairs avec contexte** :
- `Route "song_show" does not exist in routing configuration`
- `Entity "App\Entity\Song" is not a valid Doctrine entity`
- `Property "updatedAt" does not exist on entity "App\Entity\Song"`
- `Cannot write to file: /path/to/sitemap.xml (permission denied)`

---

## 9. Tests

### Structure
```
tests/
├── Unit/
│   ├── Service/
│   │   ├── SitemapGeneratorTest.php
│   │   ├── XmlWriterTest.php
│   │   └── SitemapIndexWriterTest.php
│   ├── Provider/
│   │   ├── StaticRouteUrlProviderTest.php
│   │   └── EntityRouteUrlProviderTest.php
│   └── Model/
│       └── SitemapUrlTest.php
├── Integration/
│   ├── SitemapGenerationTest.php  # Test end-to-end avec vraies entités
│   └── SitemapIndexTest.php       # Test génération index
└── Fixtures/
    └── Entity/
        └── TestEntity.php
```

### Stratégie de tests
- **Unit tests** : Mock Doctrine, Router, PropertyAccessor
- **Integration tests** : SQLite in-memory + vraies entités
- **Edge cases** :
  - 0 URL
  - Exactement 50k URLs (limite index)
  - > 50k URLs sur une seule source
  - Propriétés nulles (`lastmod`, `lastmodProperty`)
  - Route introuvable
  - Entité sans propriété demandée
  - Caractères spéciaux dans les URLs (échappement XML)

---

## 10. Qualité de code

### Outils
- **PHPStan Level 9** : `composer phpstan`
- **PHP-CS-Fixer (PSR-12)** : `composer cs-fix`
- **PHPUnit 12+** : `composer test`
- **Script QA** : `composer qa` (lance tout)

### Standards
- Type strict sur tout (`declare(strict_types=1)`)
- Pas de `mixed`, typer au maximum
- Readonly properties quand possible (DTOs)
- Enums pour les valeurs fixes (`ChangeFrequency`)
- PHPDoc sur les interfaces et méthodes publiques
- Pas de commentaires évidents, uniquement logique complexe

---

## 11. Documentation

### Fichiers à créer
- ✅ `PLAN.md` (ce fichier)
- ✅ `AGENTS.md` : Guide pour développeurs et AI agents (comme doctrine-export-bundle)
- ✅ `README.md` : Installation, configuration, exemples d'usage
- ✅ `CHANGELOG.md` : Historique des versions (format Keep a Changelog)
- ✅ `CONTRIBUTING.md` : Guidelines de contribution
- ✅ `LICENSE` : MIT License

### Sections du README
1. **Installation** : `composer require ecourty/sitemap-bundle`
2. **Configuration** : Exemple YAML complet
3. **Usage** :
   - Génération à la volée (controller)
   - Génération statique (command)
4. **Advanced** :
   - Sitemap index
   - Méthodes repository custom
   - Conditions DQL
5. **Performance** : Streaming, mémoire, cache
6. **Requirements** : PHP 8.3+, Symfony 6.4+

---

## 12. Roadmap de développement

### Phase 1 : Structure de base ✅
- [x] Fichiers de base (PLAN.md, AGENTS.md, composer.json)
- [x] Structure des répertoires
- [x] Configuration Symfony DI
- [x] Enum `ChangeFrequency`
- [x] DTOs (`SitemapUrl`, `StaticRouteConfig`, `EntityRouteConfig`)
- [x] Interfaces (`SitemapGeneratorInterface`, `UrlProviderInterface`)

### Phase 2 : Génération simple
- [ ] `XmlWriter` (sitemap simple)
- [ ] `StaticRouteUrlProvider`
- [ ] `UrlProviderRegistry`
- [ ] `SitemapGenerator` (version simple, sans index)
- [ ] Configuration YAML (routes statiques uniquement)
- [ ] Tests unitaires

### Phase 3 : Support entités Doctrine
- [ ] `EntityRouteUrlProvider`
- [ ] Support `query_builder_method`
- [ ] Support `conditions` (DQL)
- [ ] Streaming avec `toIterable()`
- [ ] Tests avec fixtures Doctrine

### Phase 4 : Sitemap index
- [ ] `SitemapIndexWriter`
- [ ] Logique de split par source
- [ ] Logique de chunk (> 50k par source)
- [ ] Configuration `use_index` et `index_threshold`
- [ ] Tests d'intégration sitemap index

### Phase 5 : Controller & Command
- [ ] `SitemapController`
- [ ] `DumpSitemapCommand` (avec publicDir injection)
- [ ] Confirmation overwrite fichier existant
- [ ] Tests fonctionnels

### Phase 6 : Exceptions & Validation
- [ ] Hiérarchie d'exceptions
- [ ] Validation de configuration
- [ ] Tests des exceptions

### Phase 7 : Qualité & Documentation
- [ ] PHPStan Level 9 clean
- [ ] PHP-CS-Fixer appliqué
- [ ] Coverage > 90%
- [ ] README complet avec exemples
- [ ] CHANGELOG.md
- [ ] GitHub Actions CI (PHPStan, CS, Tests)

### Phase 8 : Optimisations (v2.x)
- [ ] Cache HTTP (ETag, Last-Modified)
- [ ] Compression gzip optionnelle
- [ ] Support sitemap images/videos
- [ ] Interface web d'admin (optionnel)

---

## 13. Décisions techniques importantes

### Pourquoi un sitemap par source dans l'index ?
- **Clarté** : Facile de debugger quelle source pose problème
- **Maintenabilité** : Régénérer uniquement les sitemaps modifiés
- **Flexibilité** : Exclure une source temporairement
- **Norme** : Respecte la limite de 50k URLs par fichier

### Pourquoi `toIterable()` au lieu de `findAll()` ?
- **Mémoire** : 100k entités en RAM = crash
- **Performance** : Hydratation progressive via générateur Doctrine
- **Scalabilité** : Fonctionne avec 1M d'entités

### Pourquoi `ChangeFrequency` en enum ?
- **Type safety** : Évite les typos (`dayly` vs `daily`)
- **IDE support** : Autocomplétion
- **Validation** : Erreur à la compilation, pas au runtime

### Pourquoi `lastmodRelative` en string (`-2 days`) ?
- **Flexibilité** : Config statique qui reste dynamique
- **Simplicité** : Pas besoin de recalculer la config chaque jour
- **Clarté** : Plus lisible que `lastmod: '@last_monday'`

### Pourquoi séparer `XmlWriter` et `SitemapIndexWriter` ?
- **SRP** : Une classe, une responsabilité
- **Testabilité** : Tester indépendamment
- **Réutilisabilité** : `XmlWriter` peut être utilisé seul

---

## 14. Limites et améliorations futures

### Limites de la v1.0
- Pas de support images/vidéos dans le sitemap
- Pas de cache HTTP (ETag, Last-Modified)
- Pas de compression gzip automatique
- Pas d'interface web d'administration
- Pas de support multi-langue (hreflang)
- Une seule méthode de repository OU conditions DQL (pas les deux en même temps)

### Améliorations v2.x
- Support `<image:image>` et `<video:video>`
- Cache Symfony avec invalidation smart
- Command `ecourty:sitemap:ping` (ping Google/Bing)
- Support `hreflang` pour sites multilingues
- Dashboard Symfony UX pour monitorer les sitemaps
- Support routes avec plusieurs paramètres dynamiques

---

## 15. Compatibilité et dépendances

### Requirements strictes
```json
{
    "require": {
        "php": ">=8.3",
        "ext-xmlwriter": "*",
        "symfony/config": "^6.4|^7.0|^8.0",
        "symfony/dependency-injection": "^6.4|^7.0|^8.0",
        "symfony/http-kernel": "^6.4|^7.0|^8.0",
        "symfony/console": "^6.4|^7.0|^8.0",
        "symfony/routing": "^6.4|^7.0|^8.0",
        "symfony/property-access": "^6.4|^7.0|^8.0",
        "doctrine/orm": "^3.0|^4.0",
        "doctrine/doctrine-bundle": "^2.18|^3.1"
    }
}
```

### Dev dependencies
```json
{
    "require-dev": {
        "phpunit/phpunit": "^12.0",
        "symfony/framework-bundle": "^6.4|^7.0|^8.0",
        "symfony/phpunit-bridge": "^6.4|^7.0|^8.0",
        "phpstan/phpstan": "^2.0",
        "phpstan/phpstan-symfony": "^2.0",
        "phpstan/phpstan-doctrine": "^2.0",
        "phpstan/phpstan-phpunit": "^2.0",
        "friendsofphp/php-cs-fixer": "^3.40"
    }
}
```

---

## 16. Checklist avant release 1.0.0

- [ ] Toutes les phases 1-7 complétées
- [ ] PHPStan Level 9 sans erreur
- [ ] PHP-CS-Fixer sans erreur
- [ ] Coverage tests > 90%
- [ ] README complet avec exemples
- [ ] CHANGELOG.md avec version 1.0.0
- [ ] AGENTS.md pour AI agents
- [ ] LICENSE MIT
- [ ] GitHub Actions CI configuré
- [ ] Tag Git `v1.0.0`
- [ ] Publié sur Packagist
- [ ] Annonce sur Twitter/LinkedIn

---

**Date de création** : 2026-01-05  
**Auteur** : Édouard Courty  
**Version du plan** : 1.0
