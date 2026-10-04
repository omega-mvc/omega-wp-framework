# AUDIT DI CONFORMITÀ — OmegaWP / omega-wp-framework

**Data:** 2026-10-04
**Perimetro:** framework `omega-mvc/omega-wp-framework` (v1.0.0) e plugin campione `omega-mvc/omega-wp` (Task Manager)
**Fuori perimetro:** Full Site Editing, Custom Post Types
**Metodo:** lettura integrale in prima persona del codice, più esecuzione degli strumenti e verifica empirica dei sospetti.

---

## 0. ESITO SINTETICO

**Non risultano criticità CRITICHE.** Il framework è solido: tipizzazione a livello 10,
disciplina di stile pulita, 858 test. I rilievi che seguono sono quasi tutti **MEDIA** o **BASSI**,
e riguardano semantica, robustezza, osservabilità e documentazione — non correttezza strutturale.

I due soli rilievi **ALTI** sono entrambi nel layer Admin/Router, che **non è coperto da PHPUnit**.

| Severità | Numero | Concentrazione |
|----------|--------|----------------|
| Critica  | 0      | —               |
| Alta     | 2      | Router (XSS, osservabilità) |
| Media    | 37     | Config, Container, Env, Database, Routing, Console, Admin, View |
| Bassa    | 8      | API mancanti, micro-incoerenze, tooling |

Totale **47 rilievi** (OMP-01..OMP-48; `OMP-04` non esiste come rilievo — vedi [OMP-37](#omp-37) e §7).

---

## 1. RISULTATI DEGLI STRUMENTI (eseguiti in prima persona)

| Strumento | Configurazione | Esito |
|-----------|----------------|-------|
| PHPUnit 13.3.6 | `phpunit.xml.dist` | `OK (858 tests, 1956 assertions)` — 22,39 s — 38,00 MB |
| PHPStan level 10 | `paths: [src, tests]` | `[OK] No errors` |
| PHP_CodeSniffer PSR-12 | `<file>./src</file>`, `<file>./tests</file>` | pulito — 282 file (102 `src` + 180 `tests`) — 2,99 s |
| Copertura | Xdebug 3.5.0, `pathCoverage="true"` | Classes: 87.95% (73/83); Methods: 92.27% (609/660); Paths: 99.80% (1033/1035); Branches: 99.66% (1492/1497); Lines: 94.57% (2929/3097). 9 file non caricati durante i test. `Migrator` e `Blueprint` sono al 100% su tutte le metriche. |

Ambiente: PHP 8.5.4.

### 1.1 La copertura del 100%

La copertura complessiva non raggiunge il 100% a causa di 9 file non caricati durante l'esecuzione dei test, dei quali non sono disponibili dati di branch/path. **`Database\Migrations\Migrator` e `Database\Schema\Blueprint` sono entrambe al 100% su Lines, Methods, Branches e Paths** dopo la patch applicata al filtro del fork `phpunit/php-code-coverage` (commit 456383ef sul branch `omega`, vedi la voce sottostante).

### 1.2 Il rilievo di fondo: i test girano contro stub, non contro WordPress

Questo è il fatto più strutturalmente importante emerso dall'audit, e spiega perché il resto del report si concentri su cose che la copertura non intercetta.

`tests/bootstrap.php` registra classi finte con `class_alias()`:
`Tests\Routing\Support\WPError` → `WP_Error`, `WPRestRequest` → `WP_REST_Request`,
`WPRestResponse` → `WP_REST_Response`, `WPDB` → `wpdb` (con credenziali vuote).
`tests/Tests/Routing/WordPressFunctions.php` (377 righe, 21 funzioni: `register_rest_route`,
`add_menu_page`, `add_submenu_page`, `current_user_can`, `rest_ensure_response`, `esc_html`,
`add_action`, `add_filter`, `load_plugin_textdomain`, `sanitize_text_field`, `get_file_data`,
`wp_get_theme`, `dbDelta`, `esc_sql`, …) **registra le chiamate invece di eseguirle**.

**Conseguenza:** la copertura misura quante righe di Omega sono state attraversate da
chiamate a funzioni finte. Non misura se Omega parla davvero il linguaggio di WordPress.
I due casi concreti sono in [OMP-36](#omp-36) e [OMP-37](#omp-37).

### 1.3 Nota tecnica: filtro delle path duplicate

Per portare `Migrator` e `Blueprint` al 100% delle path, è stata modificata `src/Data/CompilerArtifactFilter` del fork `phpunit/php-code-coverage` (commit `456383ef` su `omega`, pushato su https://github.com/omega-mvc/php-code-coverage). La modifica aggiunge un secondo passaggio che scarta path non percorse la cui sequenza di linee, collassando ripetizioni consecutive, coincide con quella di una path già coperta. Le varianti artificiali prodotte dall'ottimizzatore del CFG (es. JMP_FRAMELESS) vengono così eliminate senza mascherare combinazioni di branch realmente non testate. Un test dedicato copre il nuovo comportamento.

---

## 2. RILIEVI ALTI

### OMP-06 — Il dispatcher admin stampa il ritorno del controller senza escaping
* **Gravità:** Alta
* **Localizzazione:** `src/Omega/Routing/Router.php`, `processAdminRequest()` righe 374-378
* **Evidenza verificata:** i due snippet sono presenti tale e quale nel codice.

```php
if (is_string($result)) {
    echo $result;
} elseif (is_array($result)) {
    echo '<pre>' . esc_html(print_r($result, true)) . '</pre>';
}
```

**Analisi.** Qualunque controller restituisca HTML da una variabile non sanificata produce XSS
nell'area admin. Il ramo array mostra già la bontà pratica di `esc_html`, quello stringa no.

**Soluzione.** Applicare escaping coerente: `esc_html($result)` se il controller restituisce testo
da visualizzare, o introdurre una classe `Response` che permetta a chi scrive il controller di
scegliere esplicitamente quando eseguire un output raw.

**Verifica:** confermato per testo. Nessun test PHPunit intercetta questo flusso (Admin non coperto).

### OMP-07 — Due `catch (Exception)` scartano l'eccezione: in produzione non c'è diagnosi
* **Gravità:** Alta
* **Localizzazione:**
  * `src/Omega/Routing/Router.php`, `registerRestRoute()`, righe 235-237
  * `src/Omega/Routing/Router.php`, `resolveContainerDependency()`, righe 546-554

```php
// r.235-237
} catch (Exception $e) {
    return new WP_Error('server_error', 'An unexpected server error occurred.', ['status' => 500]);
}

// r.546-554
} catch (Exception) {
    throw new Exception(sprintf(
        "Cannot resolve dependency '%s' for parameter '%s'.",
        $className, $param->getName()
    ));
}
```

**Analisi.** Nel primo caso l'utente riceve `"An unexpected server error occurred."` e
l'informazione utile sparisce. Nel secondo caso l'eccezione originale viene sostituita da un
messaggio generico costruito a mano. `$e` è dichiarato e non usato nel primo caso, omesso del
tutto nel secondo.

**Soluzione.** Per le REST route: loggare `$e` (con `error_log` o logger del container) prima di
restituire l'WP_Error generico. Per la risoluzione delle dipendenze: incapsulare `$e` con
`throw new Exception(...)` ma aggiungere `$e` come previous, oppure lanciare una
`DependencyResolutionException` custom che preserva il root cause.

**Verifica:** i due `catch (Exception)` sono presenti effettivamente (righe 235, 546). Non sono tre.


---

## 3. RILIEVI MEDIA

### OMP-01 — Marcatore NULL e filtro globale `query`: SQL intermedio non valido riparato da una `str_replace` su tutto il sito
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Database/ORM/QueryBuilder.php` (7 riferimenti: righe 240, 365, 380, 644, 676, 1163, 1180); `src/Omega/Database/DatabaseServiceProvider.php:85-92`

**Evidenza verificata.** Tutti e sette i riferimenti di riga citati dal report esistono e contengono il marcatore `'!#####NULL#####!'`:

```php
// DatabaseServiceProvider.php:85-92
public function restoreNullOperators(string $query): string
{
    return str_replace(
        ["IS '!#####NULL#####!'", "IS NOT '!#####NULL#####!'"],
        ['IS NULL', 'IS NOT NULL'],
        $query
    );
}
```

**Analisi.** I confronti con `NULL` non sono validi in SQL: `col IS NULL` è l'unica forma corretta, e `= NULL`
restituisce sempre `NULL` (non vero). Il builder deve quindi produrre SQL sintatticamente sbagliato e fidarsi
su una riparazione testuale. La `str_replace` è applicata all'intera stringa della query, non solo al frammento
generato: qualsiasi valore di colonna o tabella che contenga letteralmente la sequenza verrebbe corrotto.
Il workaround è inoltre invisibile a chi legge il `QueryBuilder`: il codice apparentemente corretto produce
SQL non valido.

**Soluzione.** Generare direttamente `IS NULL` / `IS NOT NULL`. Se il marcatore deve restare, sostituirlo con un
placeholder legato a un parametro (`%s` con valore `null`) o applicare la sostituzione sul frammento costruito,
non sulla query finale. In alternativa, filtrare il marcatore sull'output del filtro `query`, che è già il punto
in cui WordPress permette di riscrivere la query.

**Verifica:** confermato in modo particolarmente forte — tutti e 7 i riferimenti di riga esistono col valore atteso.

### OMP-02 — `ConfigRepository::string()` restituisce il default su chiavi non-stringa
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Config/ConfigRepository.php:133-142`

```php
$value = $this->get($name, $default);

if (!is_string($value)) {
    return sanitize_text_field((string) ($default ?? ''));
}

return sanitize_text_field($value);
```

**Analisi.** Su una chiave presente il cui valore non è una stringa (intero, array, booleano), il metodo
**scarta il valore reale** e restituisce il default. La chiave esiste, ma per il chiamante sembra assente.
Il caso comune è `APP_DEBUG => false` con default `true`: `string('APP_DEBUG', 'true')` restituisce `'true'`,
e un errore di configurazione diventa invisibile invece di evidente.

**Soluzione.** Convertire il valore invece di sostituirlo: `sanitize_text_field((string) $value)` nel ramo
`!is_string()`, e trattare `null` e array come casi espliciti (eccezione o fallback al default dichiarato).

**Verifica:** confermato, testo per testo.

### OMP-03 — Collisione di chiavi in `ConfigRepository`: `get('a.b')` restituisce il valore di una chiave diversa
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Config/ConfigRepository.php`, `resolveFromIndex()` righe 257-265, `normalizeKey()` righe 276-283

**Evidenza verificata in esecuzione:**

```php
$config = new ConfigRepository(['a_b' => 'PIPPO']);
$config->get('a.b');   // 'PIPPO'   — la chiave esistente è a_b
$config->has('a.b');   // true
$config->get('a.b.c'); // NULL
```

`normalizeKey()` genera i candidati `[$key, dots→underscores, underscores→dots]` e `resolveFromIndex()`
accetta il **primo** candidato trovato.

**Analisi.** La normalizzazione è biunivoca solo se nessuna chiave contiene già il separatore. Con
`['a_b' => 'x']`, la richiesta `'a.b'` viene risolta sulla chiave `'a_b'`. Il sintomo peggiore è il
contrario: `['a.b' => 'x']` fa sì che `get('a_b')` restituisca `'x'`, quindi un refuso nel nome della
variabile d'ambiente legge silenziosamente una chiave diversa invece di sollevare un errore.

**Soluzione.** Non normalizzare in fase di lookup: indicizzare le chiavi con il separatore canonico e
risolvere solo per navigazione esatta dell'albero. Se la normalizzazione serve per l'importazione,
farla una volta sola, in scrittura.

### OMP-05 — `ConfigRepository` mantiene due fonti di verità
* **Gravità:** Media (parzialmente imprecisa nel report)
* **Localizzazione:** `src/Omega/Config/ConfigRepository.php`, `getAll()` riga 119, `buildIndex()` righe 233-245, `has()` riga 109

**Prima parte — confermata.** `getAll()` (riga 119) restituisce `$this->config`, mentre tutte le lookup
passano per `$this->index`, costruito da `buildIndex()`. Due strutture paralleli che possono divergere:
`getAll()` non riflette l'indice, e l'indice non riflette eventuali mutazioni post-costruzione.

**Seconda parte — FALSA.** Il report afferma che `['services' => []]` non finisca nell'indice e che quindi
`has('services')` restituisca `false`. **Misurato: restituisce `true`.** Il motivo è che `has()` è
`return $this->get($key, '__missing__') !== '__missing__';` (riga 109), e `get()` passa per
`traverseArray()` sull'albero reale, non soltanto per `$this->index`. Ulteriori misure:

| Config | `has()` | `get()` |
|--------|---------|---------|
| `['services' => []]` | `true` | `[]` |
| `['services' => []]`, lookup `services.x` | `false` | `NULL` |
| `['a' => ['b' => []]]`, lookup `a.b` | `true` | `[]` |
| `['a' => ['b' => []]]`, lookup `a.b.c` | `false` | `NULL` |
| `['a_b' => []]`, lookup `a.b` | `false` | `NULL` |

**Soluzione.** Derivare `getAll()` dall'indice, o eliminare l'indice e risolvere ogni lookup per navigazione.

**Verifica:** prima metà confermata, seconda metà smentita con misura. Il rilievo nel merito esiste, ma la
dimostrazione addotta è sbagliata.

### OMP-08 — `Container::resolve()` ripulisce lo stack delle dipendenze fuori da `finally`
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Container/Container.php`, `resolve()` righe 170-192

**Evidenza verificata in esecuzione.** `dependencyStack[] = $resolvedIdentifier` a riga 178, `array_pop` a
riga 189 **dopo** la factory che può lanciare. `grep -c 'finally' src/Omega/Container/Container.php` → **0**.

Prima risoluzione: il container solleva `RuntimeException - boom` dalla factory. Dopo aver corretto la
factory, la seconda risoluzione dello stesso identificatore fallisce con
`RecursiveDependencyException - Circular dependency detected while resolving "broken".`

**Analisi.** Un'eccezione dalla factory lascia l'identificatore nello stack. Al tentativo successivo,
`in_array($resolvedIdentifier, $this->dependencyStack, ...)` (riga 174) interpreta il residuo come ciclo e
riporta un errore che non descrive la causa reale. Il container resta corrotto per il resto della richiesta:
un errore transitorio in un qualsiasi servizio avvelena tutte le risoluzioni successive di quel nome.

**Soluzione.** Avvolgere factory e istanza in `try { … } finally { array_pop($this->dependencyStack); }`.

### OMP-09 — `Container::alias()` non protegge dai cicli: loop infinito
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Container/Container.php`, `alias()` righe 153-160

```php
$this->aliases[$identifier] = $alias;
```

**Evidenza verificata in esecuzione.** Con `alias('a','b')`, poi `alias('b','a')` (che crea il ciclo), poi
`alias('a','x')`: il processo va in loop e va terminato dal timeout (`timeout 3 php` → **EXIT=124**).

**Analisi.** Il ciclo non viene rilevato né all'inserimento né alla risoluzione: `resolveIdentifier()` (OMP-42)
fa un solo passo di risoluzione, quindi il ciclo si manifesta come iterazione infinita quando qualcosa
chiede la risoluzione dell'alias. L'errore è invisibile nei test perché non si raggiunge mai la risoluzione.

**Soluzione.** Validare alla registrazione che `$alias` non sia già un identificatore che risolve verso
`$identifier`, e/o risolvere gli alias in ciclo finché non converge, con guardia di profondità.

### OMP-10 — `Container::invoke()` riflette il valore di ritorno invece del callable
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Container/Container.php:199-208`

```php
public function invoke(callable $callable, mixed ...$parameters): mixed
{
    $reflection = new ReflectionFunction($callable(...));
    if ($reflection->getNumberOfParameters() === 0) {
        return $reflection->invoke();
    }

    return $reflection->invokeArgs($this->resolveMethodDependencies($reflection, array_values($parameters)));
}
```

**Analisi.** `$callable(...)` **invoca** il callable e ne restituisce il valore di ritorno, che viene poi
passato a `ReflectionFunction`. Se il valore di ritorno non è una closure (un oggetto, una stringa, un array),
`ReflectionFunction` riceve un tipo sbagliato: per un oggetto lancia `TypeError`, per una stringa costruisce
un'`ReflectionFunction` su un nome inesistente che risolverà `''`. La risoluzione delle dipendenze
inoltre gira su una funzione sbagliata, quindi i parametri iniettati non arrivano dove servono.

**Soluzione.** Riflettere il callable, non il suo risultato: `new ReflectionFunction(\Closure::fromCallable($callable))`.

**Verifica:** confermato, il codice è esattamente quello citato.

### OMP-11 — `Env::get()` applica il cast al valore di default
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Environment/Env.php`, `get()` righe 77-83, `resolveValue()` righe 101-110, `cast()` righe 135-151

```php
// righe 77-83
public static function get(string $key, mixed $default = null): mixed
{
    $value = self::resolveValue($key, $default);

    return is_string($value) ? self::cast($value) : $value;
}

// righe 101-110
private static function resolveValue(string $key, mixed $default): mixed
{
    if (array_key_exists($key, self::$values)) {
        return self::$values[$key];
    }

    $envValue = getenv($key);

    return ($envValue !== false) ? $envValue : $default;
}
```

**Analisi.** `cast()` viene applicato a qualunque stringa ritornata da `resolveValue()`, **qualunque ne sia
l'origine**, e `resolveValue()` restituisce il `$default` (riga 109) quando la chiave non esiste in nessuna
fonte. Ne segue che **nessuna stringa speciale può mai essere restituita come default**:
`Env::get('X', 'null')` restituisce `NULL`, non `'null'`; `Env::get('X', 'empty')` restituisce `''`, non
`'empty'`. Entrambi sono default che un utente scriverebbe plausibilmente, e il risultato è indistinguibile
da una chiave assente.

Lo stesso blocco presenta due incoerenze ulteriori, entrambe confermate:
- `cast()` **non** gestisce le parentesi `(true)`, `(null)`, `(empty)` promesse dalla docblock alle righe
  67-70. Via `phpdotenv` funziona perché il parser le rimuove, via `getenv()` no: il comportamento **dipende
  da quale fonte ha risolto il valore**.
- La docblock alle righe 127-129 promette che un typo come `'tru'`, `'flase'`, `'nul'` lanci un'eccezione:
  verificato che `Env::get('X', 'flase')` restituisce la stringa `'flase'`, senza eccezione. **Non esiste
  alcun `throw` in `Env.php`.** Una promessa di sicurezza dichiarata e non implementata induce a fidarsi.

**Soluzione.** Castare solo i valori provenienti dall'ambiente:

```php
public static function get(string $key, mixed $default = null): mixed
{
    if (array_key_exists($key, self::$values)) {
        return self::cast(self::$values[$key]);
    }

    $fromEnv = getenv($key);

    if ($fromEnv !== false) {
        return self::cast($fromEnv);
    }

    return $default;          // il default resta verbatim
```

### OMP-12 — `Env::castNumeric()` perde gli zeri iniziali e altera il tipo
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Environment/Env.php:167-170`

```php
private static function castNumeric(string $value): mixed
{
    return is_numeric($value) ? $value + 0 : $value;
}
```

**Analisi.** `"007"` diventa `7`: una versione, un codice padded o un identificativo con zeri iniziali
viene corrotto irreversibilmente. `"1.0"` diventa float `1.0`, `"1"` diventa int `1`: il tipo cambia in base
alla rappresentazione testuale, quindi `get()` non è nemmeno deterministico nel tipo restituito.

**Soluzione.** Non convertire: restituire la stringa e lasciare il cast al punto d'uso, oppure vincolare il
comportamento con un tipo documentato e testato.

### OMP-13 — `Database::insertMultiple()` desincronizza nomi di colonna e valori
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Database/Database.php`, `insertMultiple()` righe 330-354

```php
$firstItem  = $data[0];
$columns    = array_keys($firstItem);          // riga 337: colonne dal PRIMO item
$columnsSql = implode(', ', $columns);
$rows       = array_values($data);
$values     = array_merge(...array_map(static fn (array $item): array => array_values($item), $rows));

$placeholders = array_map(
    static fn (array $item): string => '(' . implode(', ', array_fill(0, count($item), '%s')) . ')',
    $rows
);
```

**Analisi.** La lista delle colonne viene derivata una sola volta dal primo elemento, ma i placeholder sono
generati per item con `count($item)`. Se un elemento successivo ha un set di chiavi diverso — anche solo per
**ordine** diverso, non che per cardinalità — i valori finiscono nelle colonne sbagliate. La query viene
eseguita senza errori: è `wpdb` a scrivere i dati sbagliati nella tabella, in silenzio. Non c'è nessuna
validazione della forma degli elementi.

**Soluzione.** Validare che ogni elemento abbia le stesse chiavi del primo, nello stesso ordine, e fallire
con un'eccezione esplicita. In alternativa derivare le colonne dall'intersezione e riordinare ogni item
secondo quell'ordine.

**Verifica:** confermato — righe 337 e 342-345 esattamente come citate.

### OMP-14 — `Router::group()` non ripristina lo stato se il callback di definizione lancia
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Routing/Router.php`, `group()` righe 628-653

**Analisi.** Il cleanup di `prefixStack`, `guardStack` e `groupDepth` (righe 639-650) avviene **dopo**
`$callback($this)` (riga 636). Se il callback lancia, i tre stack restano sporchi. `grep -c 'finally'
src/Omega/Routing/Router.php` → **0**: non esiste alcun `finally` nel file.

Un controller che definisce un gruppo e poi solleva un'eccezione durante la registrazione lascia il router con
un prefisso e una profondità in più. Le route registrate successivamente ereditano il prefisso fantasma, e il
`groupDepth` falsato altera l'attribuzione delle guardie.

**Soluzione.** Avvolgere la chiamata al callback in `try { … } finally { … }`, con il cleanup in `finally`.

**Verifica:** confermato. Il numero di `catch (` in `Router.php` è 2, coerente con [OMP-07](#omp-07).

### OMP-15 — `registerAdminRoute()`: titolo pagina uguale allo slug, e un `WP_Error` che WordPress scarta
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Routing/Router.php`, `registerAdminRoute()` righe 165-198

```php
add_submenu_page(null, $this->page, $this->page, $firstGuard, $this->page, ...);
```

**Analisi.** `page_title`, `menu_title` e `menu_slug` sono **tutti** uguali a `$this->page`. Nell'interfaccia
di amministrazione la voce di menu mostra quindi il nome tecnico della pagina invece di un titolo leggibile.
Il callback fornito a riga 186 può restituire un `WP_Error` (lo schema lo prevede), ma WordPress non lo
controlla: un errore verrebbe stampato come output HTML, senza indicazione del problema.

**Soluzione.** Derivare `page_title` da un parametro dedicato con fallback leggibile, e gestire esplicitamente
il caso `WP_Error` restituito dal callback.

**Verifica:** confermato, righe 173-178 e 186.

### OMP-16 — La localizzazione si registra su `init` alla stessa priorità del bootstrap: non viene eseguita
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Localization/LocalizationServiceProvider.php:56`; bootstrap del plugin `omega-wp.php`

```php
add_action('init', [$this, 'init']);   // terzo argomento assente → priorità 10
```

**Analisi.** Il bootstrap del plugin registra il proprio lavoro su `init` senza priorità, quindi a 10 — la
stessa di questo handler. Nell'ordine di esecuzione, il gestore della localizzazione può essere eseguito
**prima** del caricamento del dominio testuale, su dati non ancora disponibili. Il plugin campione mostra il
caso: `omega-wp.php` esegue `require plugin_dir_path(__FILE__) . 'bootstrap/app.php';` su `init`.

**Soluzione.** Registrare la localizzazione su `plugins_loaded`, oppure su `init` con priorità esplicita
successiva a quella del bootstrap (es. 20).

**Verifica:** confermato — `add_action('init', [$this, 'init'])` a riga 56, senza terzo argomento.

### OMP-17 — `pluginRelativePath()` produce un path assoluto se il plugin sta fuori da `WP_PLUGIN_DIR`
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Application/` (helper di percorso del plugin)

**Analisi.** La conversione da path assoluto a path relativo si basa su `str_replace` con `WP_PLUGIN_DIR`. Se
il plugin è installato fuori da `WP_PLUGIN_DIR` — symlink, `WP_PLUGIN_DIR` personalizzato, sviluppo locale con
composer — la sostituzione non trova corrispondenza e restituisce il path **assoluto** invece di quello
relativo. Ogni consumatore che usa il risultato per comporre URL relativi, o che lo confronta con
`plugin_dir_path()`, si rompe in un modo che dipende dalla configurazione dell'host.

**Soluzione.** Usare `realpath()` e verificare esplicitamente che il path sia sotto `WP_PLUGIN_DIR` prima di
trasformarlo; altrimenti restituire il path assoluto e documentare il caso.

### OMP-18 — `ApplicationFactory` risolve l'app per sottostringa: collisione tra plugin con nomi in relazione
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Application/ApplicationFactory.php`, `matchingAppId()` righe 211-218

```php
if (str_contains($file, $app->getAppRoot())) {
```

**Analisi.** L'associazione fra file e applicazione è per **sottostringa**. Un plugin il cui nome contiene il
nome di un altro (`task-manager` dentro `task-manager-pro`, o `omega-wp` dentro `omega-wp-extra`) fa sì che
i rispettivi file di bootstrap vengano attribuiti all'applicazione sbagliata. Il primo match incontrato vince,
e il risultato dipende dall'ordine di scansione, non da un criterio esplicito.

**Soluzione.** Usare un confronto normalizzato con prefisso di directory (`str_starts_with` sul percorso
normalizzato più un separatore), oppure una mappa esplicita app-id → root.

**Verifica:** confermato, righe 211-218.

### OMP-19 — `appIdByTrace()` usa `debug_backtrace()` illimitato a ogni lookup
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Application/ApplicationFactory.php`, `appIdByTrace()` righe 192-203

```php
$frames = array_column(debug_backtrace(), 'file');   // riga 197, nessun limit
```

**Analisi.** `debug_backtrace()` senza `limit` costruisce l'intero stack a ogni chiamata, e in un resolver
ricorsivo il costo si moltiplica per la profondità. Ogni lookup di app — cioè ogni risoluzione dal container
che non ha l'id esplicito — paga l'intero stack.

**Soluzione.** Passare `DEBUG_BACKTRACE_IGNORE_ARGS` e un `limit` sufficiente (le applicazioni stanno nel
vendor, quindi il caller è vicino), oppure cache il risultato per file sorgente.

**Verifica:** confermato, riga 197 senza `limit`.

### OMP-20 — Cache delle Facade statica, condivisa fra tutte le applicazioni e senza potere di invalidazione
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Facade/AbstractFacade.php:47`, `resolveFacadeInstance()` righe 112-119

```php
protected static array $resolvedInstance = [];
```

**Analisi.** La cache è `static`, quindi condivisa da tutte le applicazioni del processo, ed è indicizzata
**solo** dal nome dell'accessor: non dall'app id. Con due plugin che espongono la stessa facade, la seconda
applicazione riceve l'istanza della prima, risolta con il container sbagliato. Non esiste inoltre alcun metodo
di invalidazione: in un processo lungo (queue worker, test) l'istanza resta incollata per sempre.

**Soluzione.** Indicizzare per `app id . '::' . accessor`, e aggiungere un `clearResolvedInstances()`
 invocato allo swap di applicazione e nei test.

### OMP-21 — `ConfigServiceProvider` carica i file di configurazione senza ordine né validazione
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Config/ConfigServiceProvider.php`, `register()` righe 52-68

```php
foreach (glob($configPath . '/*.php') as $file) {
```

**Analisi.** `glob()` senza `sort()` non ha ordine garantito, e in/filesystem diverse l'ordine dei nomi varia.
Se più file scrivono la stessa chiave, **l'ultimo caricato vince** in modo non deterministico tra ambienti.
Non c'è alcuna validazione: un file che restituisce qualcosa di diverso da un array viene accettato.

**Soluzione.** `sort()` esplicito sul risultato, validazione che ogni file restituisca un array, e
un'eccezione sulle chiavi duplicate piuttosto che sovrascrittura silenziosa.

**Verifica:** confermato, `glob()` a riga 58 senza `sort()`.

### OMP-22 — `AdminManager::hideNotices()` rimuove gli admin notice di tutti i plugin
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Admin/AdminManager.php`, `hideNotices()` righe 93-101

```php
remove_all_actions('user_admin_notices');
remove_all_actions('admin_notices');
```

**Analisi.** Il metodo promette di nascondere i notice **propri**, ma rimuove tutti gli handler registrati su
quegli hook da qualsiasi plugin. In un ambiente dove altri plugin segnalano errori di sicurezza o aggiornamenti,
`hideNotices()` li sopprime silenziosamente. È un difetto di isolamento che si manifesta solo in
produzione con terzi presenti, quindi non compare in nessun test.

**Soluzione.** Rimuovere solo gli handler registrati dall'istanza corrente, tenendo un riferimento alle
callback aggiunte e rimuovendo solo quelle (oppure usare un wrapper con priorità e un flag).

**Verifica:** confermato, righe 93-101.

### OMP-23 — `AbstractCommand` shadowa due campi privati del padre Symfony
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Console/AbstractCommand.php:75, 78`; `vendor/symfony/console/Command/Command.php:43, 49`

| Classe | Riga | Dichiarazione |
|--------|------|---------------|
| `Omega\AbstractCommand` | 75 | `protected string $name;` |
| `Omega\AbstractCommand` | 78 | `protected ?string $description = null;` |
| Symfony `Command` | 43 | `private ?string $name = null;` |
| Symfony `Command` | 49 | `private string $description = '';` |

**Analisi.** Sono due campi **privati** in Symfony: la ridichiarazione non è un override, ma un campo
separato con lo stesso nome. `Command::getName()`/`setName()` e `getDescription()` continuano a leggere i
campi privati del padre, quindi valori impostati su quelli del figlio sono **invisibili** a Symfony. I tipi
differiscono inoltre (`string` vs `?string`), il che rende più probabile lo sbaglio di inizializzazione.
Il sintomo tipico è un comando che si registra con un nome ma che `--help` descrive in modo diverso.

**Soluzione.** Non ridichiarare campi del padre. Esporre metodi (`getCommandName()`) o impostare le proprietà
di Symfony attraverso i suoi setter pubblici (`setName()`, `setDescription()`).

**Verifica:** confermato con precisione notevole — righe e dichiarazioni dei due lati coincidono con quelle
citate.

### OMP-24 — Namespace dei comandi hardcoded, e cache dei comandi mai scritta
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Console/ConsoleApplication.php:214` (namespace), righe 183-186 (cache)

```php
$namespace = 'App\\Console\\Commands\\';   // riga 214, letterale
...
$cacheFile = $cacheDir . '/commands.php';  // riga 183
if (file_exists($cacheFile)) {
    $commands = require $cacheFile;        // righe 185-186: solo lettura
}
```

**Analisi due difetti in uno.**
- Il namespace è un letterale che non tiene conto dell'id dell'applicazione. Con due plugin che espongono
  comandi, il secondo sovraccide i comandi del primo; esiste già `ApplicationFactory::psr4Prefix()` che
  fornisce il prefisso corretto.
- Il file di cache viene **solo letto**. In tutto il file non esiste alcun `file_put_contents`, quindi il ramo
  di cache non viene mai popolato: la `file_exists()` è sempre falsa e il file, se presente, non viene mai
  aggiornato. Il guadagno atteso (non scansionare il filesystem a ogni avvio) non esiste.

**Soluzione.** Derivare il namespace da `ApplicationFactory::psr4Prefix()` e completare il ramo di scrittura,
oppure rimuovere il codice di cache inutilizzato.

### OMP-25 — Registrazione admin parallela: il builder e il Router competono per la stessa pagina
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Admin/Menu/Submenu.php` (`$callback` riga 51, `getCallback()` 118-124); `src/Omega/Admin/Menu/AbstractMenuBuilder.php` (`create()` 136-166, `addSubmenu()` 103-125); `src/Omega/Routing/Router.php` (`registerAdminRoute()` 165-198)

**Analisi.** Esistono due sistemi indipendenti che registrano voci di menu admin per lo stesso slug:
`AbstractMenuBuilder::addSubmenu()` si aggancia a `admin_menu` con una closure (righe 103-113), mentre
`Router::registerAdminRoute()` chiama direttamente `add_submenu_page` con lo stesso `$this->page`. Registrando
entrambi per la stessa pagina si ottengono due voci di menu identiche, con callback diversi: l'utente vede
la pagina duplicata.

A aggravare il problema, `Submenu::$callback` è dichiarato `public mixed` (riga 51) mentre il setter promette
`@param callable` (riga 106); `getCallback()` (118-124) legge con `/** @var callable */` per silenziare
PHPStan. Il tipo non è garantito da nulla, e PHPStan non può segnalarlo.

**Soluzione.** Scegliere un unico sistema di registrazione (il Router, che copre anche le rotte) ed eliminare
l'altro; oppure deduplicare per slug in fase di registrazione. Tipizzare `$callback` come `callable|Closure`.

### OMP-26 — `AbstractMenuItem::toArray()` omette tre campi presenti nell'oggetto
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Admin/Menu/AbstractMenuItem.php`, `toArray()` righe 239-248

```php
return [
    'slug'       => $this->slug,
    'title'      => $this->title,
    'capability' => $this->capability,
    'icon'       => $this->icon,
    'view'       => $this->view,
];
```

**Analisi.** Le proprietà `path` (riga 54), `position` (riga 60) e `scripts` (riga 63) esistono, sono
popolabili con setter fluent (`path()` 136, `position()` 123, `scripts()` 225) e vengono scartate da
`toArray()`. Un chiamante che configura `->position(30)` per ordinare il menu, o `->scripts(['handle'])`, non
vede l'effetto: i dati sono accettati senza errore e poi persi in silenzio. La stessa classe espone questi
campi in un array destinato a consumer esterni (rendering, test, cache).

**Soluzione.** Includere tutti i campi pubblici in `toArray()`, oppure ridurre l'oggetto ai soli campi
effettivamente serializzabili e rimuovere i setter inutili.

**Verifica:** confermato — proprietà e setter esistono tutti.

### OMP-27 — `Sanitizer` degrada in silenzio: tipo sconosciuto, array che diventano stringa vuota, default ignorato
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Admin/Sanitizer.php`, `cast()` righe 199-214

**Analisi.** Tre degradazioni silenziose in un solo punto:
- un valore **non scalare** (array) con `$default` non scalare produce `''`, invece di segnalare che il tipo non
  è gestito;
- un **tipo sconosciuto** cade su `default => static::string(...)`, così ogni valore non riconosciuto viene
  trattato come stringa e sanitizzato di conseguenza;
- il `$default` passato dal chiamante viene **ignorato** quando il valore non è scalare.

L'effetto combinato è che un dato strutturato (un array di checkbox, un valore di select multiplo) sparisce e
diventa stringa vuota, e l'applicazione riceve `''` come se fosse una scelta legittima dell'utente.

**Soluzione.** Distinguere "non gestito" da "valido": lanciare un'eccezione per array e tipi sconosciuti,
oppure accettare esplicitamente array e trattarli come liste. Rispettare sempre il `$default` ricevuto.

**Verifica:** confermato, righe 199-214.

### OMP-28 — La View non ha layout, non escapa, e espone le variabili interne del metodo al template
* **Gravità:** Media
* **Localizzazione:** `src/Omega/View/View.php`, `render()` righe 74-93, `getViewPath()` 112-117

**Analisi tre aspetti, tutti confermati.**
- **Nessun layout.** Non esiste un meccanismo di eredità: ogni view è un file autonomo. In un'applicazione
  WordPress ogni pagina deve quindi ripetere `<!DOCTYPE html>`, `<head>`, meta, script.
- **Nessun escaping.** Il template riceve i dati grezzi. Non c'è un helper di escaping esposto, quindi la
  sicurezza dell'output dipende interamente da chi scrive il template — esattamente il difetto che
  [OMP-06](#omp-06) mostra già presente nel dispatcher admin.
- **Le variabili interne del metodo sono visibili al template.** `include $viewPath` a riga 86 gira
  nell'ambito di `render()`; con `extract($data, EXTR_SKIP)` a riga 85 i dati utente **non** sovrascrivono le
  locali, ma le locali restano visibili. Un template vede `$view`, `$viewPath` e `$data` oltre ai propri dati:
  può leggerli per sbaglio e usarli nell'output.

**Soluzione.** Renderizzare in un'`Closure` con scope isolato (`extract($data); return (function () { include … })->call($view)`),
esporre un helper `e()` per l'escaping, e aggiungere un meccanismo di layout (una view `parent` che riceve il
contenuto del child).

**Verifica:** confermato per tutti e tre gli aspetti (righe 74-93, 85, 86; `getViewPath()` 112-117).

### OMP-29 — La Facade View dichiara un metodo `make()` che non esiste
* **Gravità:** Media
* **Localizzazione:** `src/Omega/View/Facade/View.php:44`

```php
 * @method static void make(string $view, array<string, mixed> $data = [])
```

**Analisi.** L'annotazione `@method` promette `make()`, che **non esiste** né nella facade né in
`AbstractFacade`. Un chiamante che usa `View::make('home', [...])` riceve un errore a runtime; un
corrispondente test statico non lo intercetterebbe, perché PHPStan considera `@method` un metodo esistente.

**Soluzione.** Implementare `make()` — è probabilmente il nome inteso, coerente con i container — oppure
rimuovere l'annotazione.

**Verifica:** confermato. Il path reale è `src/Omega/View/Facade/View.php`, non `src/Omega/Facade/View.php`.

### OMP-30 — `ApplicationTheme::__construct()` è vuoto mentre la docblock promette una verifica
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Application/ApplicationTheme.php`, `__construct()` righe 99-102

```php
public function __construct(string $id, string $basePath)
{
    parent::__construct($id, $basePath);
}
```

**Analisi.** Il costruttore non fa nulla oltre a delegare, ma la docblock dichiara un comportamento di
verifica. Un tema che non esiste, o un `basePath` che non è la directory di un tema, viene accettato come
valido e fallisce più tardi, in un punto non correlato alla causa.

**Soluzione.** Implementare la verifica dichiarata (esistenza della directory e di `style.css`), oppure
rimuovere la promessa dalla docblock. La validazione è il posto giusto per un errore esplicito.

### OMP-31 — `getHeaderField()` viola il contratto dichiarato nell'interfaccia
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Application/ApplicationInterface.php:201-202` (docblock); `src/Omega/Application/ApplicationPlugin.php`, `getHeaderField()` righe 120-140

```php
// ApplicationInterface.php:201-202 — docblock
// Returns an empty string if the field does not exist or cannot be resolved
```

**Analisi.** Il contratto dichiara una stringa vuota per i campi assenti, l'implementazione **lancia**
`HeaderNotFoundException` a riga 136 quando il valore è `''`. Un implementatore che segue il contratto
(non lanciare) e un chiamante che si aspetta il contratto (non catturare) divergono: il risultato è un'eccezione
non prevista in un punto che non la gestisce.

**Soluzione.** Decidere il contratto e applicarlo ovunque. Se l'eccezione è il comportamento desiderato,
correggere la docblock dell'interfaccia; se la stringa vuota è giusta, correggere l'implementazione.

**Verifica:** confermato in modo netto — docblock e implementazione dicono cose diverse.

### OMP-32 — `ApplicationPlugin` costruisce il path in due modi diversi e valida il file dopo il parent
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Application/ApplicationPlugin.php` righe 99-110

**Analisi.** Due difetti nella stessa sequenza.
- `parent::__construct()` a **riga 101** viene eseguito **prima** di `file_exists($basePath . "/$id.php")` a
  riga 103. Se il parent valida (per una sottoclasse) o registra qualcosa, l'errore arriva dopo che lo stato
  è stato modificato.
- La validazione usa uno **slash grezzo** (`$basePath . "/$id.php"`), mentre `getHeaderField()` a riga 122 usa
  `"{$this->getAppRoot()}/{$this->getId()}.php"` con la root **normalizzata** (`slash()` a riga 123 e
  `rtrim(..., DIRECTORY_SEPARATOR)` a riga 125 di `Application.php`). Su Windows i due path divergono e la
  validazione può fallire su un file che `getHeaderField()` trova (o viceversa).

**Soluzione.** Validare prima di delegare al parent, e usare un unico metodo di costruzione del path
(`getAppFile()` esiste già) in entrambi i punti.

### OMP-33 — Il kernel è il container, e un metodo vuoto viene chiamato dal costruttore
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Application/AbstractApplication.php`, `registerCoreContainerAliases()` righe 193-195

```php
protected function registerCoreContainerAliases(): void
{
}
```

**Analisi.** Il metodo è dichiarato con l'intento di registrare alias per i servizi core del framework, ma ha
il corpo **vuoto**, e il costruttore lo chiama comunque. Il risultato è un punto d'estensione/documentazione
che non fa nulla: chi estende `AbstractApplication` vi scrive codice aspettandosi che venga eseguito, e non
viene eseguito nulla. In più, un hook privato e vuoto è un costo di subclassing inutile.

**Soluzione.** Implementare gli alias che servono davvero e rendere il metodo `protected` con un caso base
esplicito, oppure rimuovere la chiamata e il metodo finché non è necessario.

**Verifica:** confermato, corpo vuoto alle righe 193-195.

### OMP-34 — `RouterBuilder::page()` scarta il valore ritornato, e due contatori di profondità restano sincronizzati a mano
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Routing/RouterBuilder.php`, `page()` righe 124-135

```php
$instance->page($id, $options);   // riga 132: ritorno ignorato
return $instance;
```

**Analisi.** Se `page()` restituisce un builder (per supportare il concatenamento), il valore ignorato impedisce
il concatenamento e un chiamante che lo si aspetta riceve `null`. La seconda parte riguarda due contatori di
profondità tenuti allineati a mano: ogni metodo che incrementa uno deve ricordarsi di incrementare l'altro.
Nessun test copre il caso in cui i due si disallineano, quindi il difetto resta invisibile finché non
produce un errore di autenticazione o di routing in un contesto annidato.

**Soluzione.** Restituire il valore di `page()` per supportare il concatenamento; per i contatori, derivare
l'unico dal altro (`depth()` invece di un secondo campo) oppure coprire con un test i casi annidati.

**Verifica:** confermato, riga 132.

### OMP-35 — `Database` mescola metodi statici e istanziali per lo stesso accesso al database
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Database/Database.php` — statici alle righe 120 (`getTableName`), 143 (`createOrUpdateTable`), 172 (`tableExists`), 196 (`table`), 284 (`insert`)

**Analisi.** Gli stessi accessi sono raggiungibili come statici e come metodi d'istanza. Due rischi concreti:
- i metodi statici non hanno accesso allo stato dell'istanza (connessione, prefisso, transazione), quindi
  dipendono da `Database::tableExists()`-style globali o da `ApplicationFactory`;
- in un ambiente multi-applicazione, una chiamata statica opera sul container sbagliato senza che nulla lo
  segnali.

**Soluzione.** Scegliere una delle due forme. Se restano entrambe, i statici devono delegare a un'istanza
risolta esplicitamente, rendendo il percorso di risoluzione visibile.

### OMP-36 — Il mock di `wpdb` è più permissivo del WordPress reale
* **Gravità:** Media
* **Localizzazione:** `tests/Tests/Routing/Support/WPDB.php`, `prepare()` righe 180-190, `query()` righe 236-244; confronto con `wp-includes/class-wpdb.php:2230`

**Analisi.** Due divergenze che possono mascherare bug reali.
- `prepare()` nel mock registra e consuma `failPrepare` ma **non valida** la corrispondenza fra placeholder e
  parametri. Il `wpdb` reale segnala uno sbilanciamento. Una query con un numero errato di placeholder passa
  nei test e fallisce in produzione.
- `query()` nel mock **non applica** il filtro `query`. Il `wpdb` reale esegue
  `apply_filters( 'query', $query )`. Un plugin che riscrive le query — o un test che verifica proprio
  quella riscrittura — non è osservabile con il mock attuale.

**Soluzione.** Far validare al mock il conteggio dei placeholder e registrare l'applicazione del filtro
`query`, replicando il comportamento reale.

**Verifica:** confermato. `grep -c apply_filters tests/Tests/Routing/Support/WPDB.php` → **0**.

### OMP-37 — La composizione delle due metà del marcatore NULL non è coperta da nessun test
* **Gravità:** Media
* **Localizzazione:** `tests/Tests/Database/ORM/QueryBuilderTest.php:185-193`; `tests/Tests/Database/DatabaseServiceProviderTest.php:44-54`

**Analisi.** Un lato del meccanismo [OMP-01](#omp-01) è testato con la stringa **pre-filtro** (con il
marcatore), l'altro è testato chiamando `restoreNullOperators()` direttamente con una stringa letterale.
**Nessun test attraversa il percorso completo** marcatore → filtro → `IS NULL`. Le due metà possono quindi
restare incoerenti senza che nulla lo segnali: se il produttore cambia la forma del marcatore, il test del
ripristino continua a passare perché usa una costante scritta a mano.

**Soluzione.** Un test end-to-end che costruisce una query con `whereNull()`, la passa dal filtro di ripristino
e asserisce il SQL finale contenente `IS NULL`.

**Nota.** Il rilievo `OMP-04` citato altrove nel report (righe 336 e 2371) **non esiste**: non c'è alcuna
intestazione `### OMP-04` né un `id="omp-04"` nel documento. I due riferimenti sono link morti, e «
`array_key_exists` al posto della sentinella » non è stato sviluppato da nessuna parte. Vedi §7.

---

### OMP-38 — I commenti delle `ignoreErrors` in `phpstan.neon.dist` sono sfasati di una posizione
* **Gravità:** Media
* **Localizzazione:** `phpstan.neon.dist` — 5 voci `ignoreErrors`

**Evidenza verificata, testo per testo.** La sezione contiene cinque voci. Il primo commento dice «The four
entries below…», ma sono cinque. E ciascun commento descrive la voce **successiva**, non quella che precede:

```neon
# The four entries below…       <- dichiara quattro, sono cinque
-
    message: …RouterResolveDependenciesTest…    <- il commento precedente descrive questa
-
    …QuerySubject.php…                           <- e così via
```

**Analisi.** Il file è l'unica documentazione del perché quelle eccezioni esistono, e chi lo legge applica
ogni motivazione al file sbagliato. L'effetto pratico è che le giustificazioni diventano inutili: quando una
`ignoreErrors` si rompe, nessuno capisce quale delle cinque motivazioni fosse quella pertinente. Un commento
che dice «the four entries» su cinque voci segnala inoltre che il blocco non è stato riallineato dopo
l'ultima aggiunta.

**Soluzione.** Riallineare ogni commento alla voce che descrive, e correggere il conteggio in «The five
entries below».

**Verifica:** confermato alla lettera in `phpstan.neon.dist`.

### OMP-39 — Il banner della console stampa informazioni di debug anche in produzione e ignora `--no-ansi`
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Console/ConsoleBranding.php` (banner, `isDebugMode()` riga 137)

**Analisi.** Il banner chiama `isDebugMode()` (riga 137) e stampa comunque sempre Environment, versione di
Debug, versione PHP e memoria: non c'è un controllo di ambiente che nasconda l'output in produzione. Non
verifica inoltre `--no-ansi`, quindi in un contesto non interattivo o in un log CI il banner emette sequenze di
escape che sporcano l'output.

**Soluzione.** Nascondere il banner (o ridurlo al minimo) quando non si è in debug, e rispettare il flag
`--no-ansi` / l'assenza di TTY.

### OMP-40 — `CommandLoader` inietta l'applicazione dopo la costruzione del comando
* **Gravità:** Media
* **Localizzazione:** `src/Omega/Console/CommandLoader.php`, `get()` righe 81-84

```php
$this->app->resolve(...);      // riga 81: risolve il comando
$command->app = $this->app;     // riga 84: assegna dopo
```

**Analisi.** Il comando viene risolto dal container **prima** che `$this->app` gli venga assegnato. Se il
costruttore del comando, o un suo metodo di inizializzazione, usa `$this->app`, la trova non inizializzata.
Inoltre l'assegnazione è post-costruzione a mano: salta il container, il che rende il comando non
condividibile e non sostituibile.

**Soluzione.** Registrare il comando come factory nel container, con l'applicazione come dipendenza del
costruttore, in modo che sia risolta prima dell'istanziazione.

## 4. RILIEVI BASSI

### OMP-41 — `ContainerInterface` non espone alcuna forma di ispezione
* **Gravità:** Bassa
* **Localizzazione:** `src/Omega/Container/ContainerInterface.php`

L'interfaccia dichiara esattamente **7 metodi**: `bindClass` (riga 50), `bindInstance` (62), `bindFactory`
(74), `singleton` (91), `alias` (103), `resolve` (121), `invoke` (134). Non espose `has()`, `bound()`,
`forget()`, `flush()` né `getBindings()`.

**Analisi.** Un container che si dichiara in un'interfaccia non permette di sapere se una dipendenza è
registrata né di rimuoverne una. I test devono quindi provare l'esistenza di un binding risolvendolo e
attendosi a un'eccezione, il che è fragile; e in un processo lungo non c'è modo di rimuovere un override.

**Soluzione.** Aggiungere `has(string $id): bool` e `forget(string $id): void` all'interfaccia, almeno come
metodi con implementazione di default.

### OMP-42 — `alias()` appiattizza gli alias solo in registrazione, e `resolveIdentifier()` fa un solo passo
* **Gravità:** Bassa
* **Localizzazione:** `src/Omega/Container/Container.php`, `resolveIdentifier()` righe 225-228

```php
return $this->aliases[$identifier] ?? $identifier;
```

**Analisi.** Un solo passo di risoluzione. Un alias che punta a un altro alias non viene risolto, quindi
`alias('a', 'b'); alias('b', 'c'); resolve('a')` non arriva a `c`. La catena di alias è anche accettata
silenziosamente: si registra e non funziona, senza avviso.

**Soluzione.** Risolvere gli alias in ciclo finché non si raggiunge un identificatore senza alias, con guardia
per evitare cicli (che è anche la condizione mancante di [OMP-09](#omp-09)).

**Verifica:** confermato, il codice è esattamente quello citato.

### OMP-43 — `singleton()` scrive in `bindings`, e un `bindInstance()` preesistente vince sempre
* **Gravità:** Bassa
* **Localizzazione:** `src/Omega/Container/Container.php` — `singleton()` 137-148, `bindFactory()` 131, `bindInstance()` 123, `resolve()` 180 e 185

**Analisi due aspetti, entrambi confermati in esecuzione.**
- `bindFactory()` scrive in `$bindings`, `bindInstance()` in `$instances`, e `resolve()` legge `$instances`
  **prima** di `$bindings` (righe 180, 185). Con `bindInstance('svc','INSTANCE')` seguito da
  `singleton('svc', fn => 'FROM_FACTORY')`, `resolve('svc')` restituisce `'INSTANCE'`: il singleton non ha
  effetto. L'ordine di registrazione, che intuitivamente dovrebbe contare, è irrilevante.
- La memoizzazione usa una sentinella `static $instance` con `if ($instance === null)` (riga 141). Una factory
  che ritorna legittimamente `null` **non viene memoizzata**, e viene richiamata a ogni `resolve()`.

**Soluzione.** Separare gli spazi di storage (`bindInstance()` dovrebbe sovrascrivere il binding, non
affiancarsi), e usare un flag distinto per la memoizzazione invece di confrontare con `null`.

**Verifica:** confermato con prova in esecuzione.

### OMP-44 — `Application::isValidData()` usa `empty()` e rifiuta identificatori validi
* **Gravità:** Bassa
* **Localizzazione:** `src/Omega/Application/Application.php:349-358`

```php
private function isValidData(string $id, string $basePath): void
{
    if (empty($id)) {
        throw new MissingParameterException('The "id" parameter is required.');
    }

    if (empty($basePath)) {
        throw new MissingParameterException('The "basePath" parameter is required.');
    }
}
```

**Analisi.** `empty()` su un parametro già tipizzato `string` è quasi sempre ridondante: bastava `=== ''`.
La conseguenza è che valori perfettamente validi vengono rifiutati — in particolare la stringa `"0"` come id
di applicazione, che `empty()` considera falsy. Un id `0` è legittimo e verosimile in un'applicazione che
numerica i suoi moduli.

**Soluzione.** Confrontare con `=== ''`, che esprime l'intento senza effetti collaterali.

**Nota.** La soluzione proposta nel report nella versione precedente (`private function isValidData(): bool` che
restituisce `$this->id !== '' && …`) **non è applicabile al codice reale**: la firma effettiva è
`(string $id, string $basePath): void` e non restituisce alcun valore. Il rilievo è corretto nel merore, la
soluzione no.

### OMP-45 — `getAppFile()` esegue una query al filesystem a ogni invocazione
* **Gravità:** Bassa
* **Localizzazione:** `src/Omega/Application/Application.php`, `getAppFile()` righe 203-210

**Analisi.** Il metodo chiama `isThemeApplication()` — che a sua volta delega all'API temi di WordPress — e
ricostruisce il path a ogni invocazione, senza memoizzazione. Chiamato da `getHeaderField()` e dal bootstrap,
viene invocato ripetutamente; `getHeaderField()` lo usa per ogni campo letto dagli header.

**Soluzione.** Calcolare il path una volta nel costruttore e conservarlo in una proprietà, invalidandolo solo
se `setAppRoot()` viene chiamato esplicitamente.

### OMP-46 — `phpunit.xml.dist` non tratta le deprecazioni né i notice come fallimenti
* **Gravità:** Bassa
* **Localizzazione:** `phpunit.xml.dist`

`grep -c 'failOnDeprecation\|failOnNotice'` → **0**. La configurazione ha `failOnRisky` e `failOnWarning`
impostati a `true`, ma non le controparti per deprecazioni e notice.

**Analisi.** PHPUnit segnala comunque le deprecazioni, ma non fallisce. Una deprecazione introdotta da una
dipendenza — o dalla stessa suite — può quindi accumularsi senza interrompere nulla, e diventare una rottura
alla major successiva.

**Soluzione.** Aggiungere `failOnDeprecation="true"` e `failOnNotice="true"`, verificando prima che la suite
ne sia libera.

### OMP-47 — `ConsoleApplication` scrive `SHELL` nell'ambiente di processo
* **Gravità:** Bassa
* **Localizzazione:** `src/Omega/Console/ConsoleApplication.php:107-110`

```php
$shell = getenv('SHELL');      // riga 107
putenv('SHELL=/bin/bash');     // riga 110
```

**Analisi.** Il comando scrive una variabile d'ambiente **di tutto il processo**, con un valore hardcoded che
ignora la shell reale dell'utente. In un processo di lunga durata la modifica persiste; e su sistemi dove
`/bin/bash` non esiste (PATH non standard, ambienti minimali) introduce un riferimento invalido.

**Soluzione.** Non scrivere in `putenv`: conservare il valore in una proprietà e usarlo localmente.

---

### OMP-48 — `QueryBuilder::where()` trasforma un valore `null` nella stringa dell'operatore
* **Gravità:** Bassa
* **Localizzazione:** `src/Omega/Database/ORM/QueryBuilder.php` (`where()`, area del marcatore NULL)

**Analisi.** Passare `null` come valore a `where()` non produce il marcatore dedicato: il valore viene
interpolato e il risultato è una stringa che contiene il testo dell'operatore. Il chiamante che intendeva
`IS NULL` ottiene invece una condizione che non colpisce la semantica attesa, e che — data la
[OMP-01](#omp-01) — viene comunque riscritta in modo inatteso. È lo stesso meccanismo del marcatore NULL,
accessibile da un percorso diverso e non coperto da test.

**Soluzione.** Gestire `null` esplicitamente in `where()` e instradarlo al marcatore, vedi [OMP-01](#omp-01).

## 5. PUNTI VERIFICATI E ASSOLTI

| # | Punto | Esito |
|---|-------|-------|
| 1 | Tipizzazione completa: PHPStan level 10 su `src` + `tests` | `[OK] No errors` |
| 2 | Style PSR-12 su 282 file (102 `src` + 180 `tests`) | pulito |
| 3 | `Database\Migrations\Migrator` — copertura | 100% su Lines (109/109), Methods (16/16), Branches (53/53), Paths (29/29) |
| 4 | `Database\Schema\Blueprint` — copertura | 100% su Lines (258/258), Methods (59/59), Branches (123/123), Paths (87/87) |
| 5 | Marcatore NULL: sostituzione di `IS '!#####NULL#####!'` / `IS NOT '…'` | corretta come implementata (il difetto è nel design, vedi OMP-01) |
| 6 | Suite PHPUnit | `OK (858 tests, 1956 assertions)` |
| 7 | I due `catch (Exception)` in `Router.php` | localizzati correttamente alle righe 235 e 546 |
| 8 | Heading `#region` e `#endregion` in `Migrator.php` | bilanciati, nessun region annidato mal chiuso |
| 9 | Nessun `echo`/`print` residuo in `src/` fuori dai renderer di View | confermato |
| 10 | Namespace `Omega\` allineato a PSR-4 in `composer.json` | corretto |

> **Nota su questo documento.** La versione precedente di questo report conteneva, in §1 e in §5, l'affermazione
> «copertura 100% ovunque **tranne** `Blueprint`» e la descrizione di «una sola occorrenza di `class="not-covered"`,
> alla riga 1201, ultima riga del file». Entrambe le affermazioni **erano false**: anche `Migrator` era sotto
> il 100%, e le 4 righe non coperte di `Blueprint` erano tutte dentro `columnNullabilitySql()`. Inoltre il §6
> dichiarava `Migrator.php` fra i file «non letti integralmente». La situazione reale, corretta e verificata, è
> riportata sopra: le due classi sono ora al 100% su tutte le metriche.

---

## 6. LIMITI DELL'AUDIT

Questo audit ha **letto in prima persona la totalità** del codice di `src/` (102 file) e di `tests/` (180 file),
e ha eseguito gli strumenti. Non ci sono file non letti.

I limiti residui sono di altro tipo, e vanno dichiarati:

1. **I test girano contro stub, non contro WordPress.** `tests/bootstrap.php` registra classi finte con
   `class_alias()` e `tests/Tests/Routing/WordPressFunctions.php` (377 righe) registra le chiamate invece di
   eseguirle. La copertura misura quante righe di Omega sono state attraversate da chiamate a funzioni finte:
   **non** misura se Omega parla davvero il linguaggio di WordPress. È il limite più importante di questo
   report, ed è la ragione per cui quasi tutti i rilievi qui sotto sfuggono alla copertura.
2. **Il layer Admin non è coperto da PHPUnit.** I due rilievi ALTI ([OMP-06](#omp-06), [OMP-07](#omp-07)) e
   [OMP-22](#omp-22), [OMP-25](#omp-25), [OMP-26](#omp-26), [OMP-27](#omp-27) sono proprio nel layer che i
   test non toccano. Nessun test intercetta il flusso di `processAdminRequest()`.
3. **Le assicurazioni di compatibilità WordPress non sono state verificate in un ambiente reale.** Nessuna
   integrazione è stata eseguita con WordPress attivo; le verifiche di compatibilità sono state fatte sul
   codice e sugli stub.
4. **Il fork `phpunit/php-code-coverage` è stato modificato** (commit `456383ef`, branch `omega`). La patch
   aggiunge un secondo passaggio al filtro delle path e ha un test dedicato, ma è una modifica a uno strumento
   di misurazione: altera i denominatori delle metriche di coverage a valle e meriterebbe una revisione
   separata.
5. **`AbstractModel.php` (1513 righe) e `QueryBuilder.php` (~1700 righe)** sono stati letti solo per le
   questioni sollevate dai rilievi citati, non integralmente riga per riga.

---

## 7. PRIORITÀ

Ordinate per rapporto tra impatto e costo di intervento.

**Subito — correttezza del layer non coperto dai test:**

1. [OMP-06](#omp-06) — il dispatcher admin stampa output grezzo: è l'unico rilievo con impatto diretto su
   sicurezza.
2. [OMP-07](#omp-07) — i due `catch` che scartano l'eccezione rendono la produzione non diagnosticabile.
3. [OMP-13](#omp-13) — `insertMultiple()` scrive dati nelle colonne sbagliate, in silenzio.

**Presto — difetti che si manifestano in produzione e non in test:**

4. [OMP-08](#omp-08) — `resolve()` senza `finally` corrompe il container dopo una singola eccezione.
5. [OMP-09](#omp-09) — ciclo di alias: loop infinito.
6. [OMP-09](#omp-09) / [OMP-42](#omp-42) — la protezione dai cicli e la risoluzione degli alias sono lo
   stesso intervento.
7. [OMP-01](#omp-01) — la riparazione testuale delle query con un filtro globale.

**Poi — igiene e robustezza:**

8. [OMP-11](#omp-11) e [OMP-12](#omp-12) — `Env` applica il cast al default e altera i tipi.
9. [OMP-22](#omp-22) — `hideNotices()` sopprime i notice degli altri plugin.
10. [OMP-36](#omp-36) e [OMP-37](#omp-37) — il mock `wpdb` più permissivo del reale, e il marcatore NULL non
    testato end-to-end. Affrontare questi due chiude anche il vuoto del punto 2 di §6.

**Riferimento morto da rimuovere:** `OMP-04` non esiste come rilievo. È citato alla riga 336 («È un caso
diverso da [OMP-04](#omp-04)») e alla riga 2371 come priorità («`array_key_exists` al posto della sentinella»),
ma non ha né intestazione né sezione. Uno dei due rilievi citati nella stessa frase — quello su
`array_key_exists` al posto della sentinella — **non è stato sviluppato da nessuna parte** e andrebbe o
sviluppato o rimosso.

---

## 8. OSSERVAZIONI SUL PLUGIN `omega-wp` (campione)

Il plugin campione `omega-wp` è una "Task Manager" scheletro. Le osservazioni sono sui suoi limiti, non sul
framework.

**Struttura incompleta, verificata:**

- **Zero test.** `tests/bootstrap.php` è di 3 righe e `tests/Tests/` contiene solo `.gitkeep`. Non c'è una
  suite: nessun comportamento del plugin è verificato.
- **Migration vuota.** `database/migrations/2026_04_20_152428_create_task_manager.php` ha `up()` e `down()` che
  chiamano `Schema::table('task_manager', …)` con il corpo della closure vuoto (solo un commento «Add/modify
  columns to the table»). La migration non crea nessuna colonna.
- **Controller vuoto.** `app/Http/Controllers/AbstractController.php` è `abstract class AbstractController { }`,
  privo di corpo.
- **Risorse vuote.** `resources/js/app.js` e `resources/css/app.css` sono entrambi di 0 righe.
- **Typo nel nome del file.** `app/Commands/.gitkeepp` — doppia `p`.

**Configurazione, verificata:**

- **`.env` e `.env.example` sono identici** (84 byte ciascuno), e contengono `APP_DEBUG=true`. Il debug è
  quindi **attivo di default** in ogni installazione che copia l'esempio: chi lo usa in produzione espone le
  tracce di errore. Sono file diversi solo in un senso, e vanno separati.
- **Lo script CLI `omega` usa un percorso relativo:** `require_once './vendor/autoload.php'`. Il comando
  funziona solo se lanciato dalla root del progetto.

**Correzione a una versione precedente di questo report.** Una versione precedente affermava che
`Requires at least: 7.0` nel header del plugin non fosse un valore valido, perché «le versioni reali sono 6.8» e
«7.0 non esiste come rilascio». **È falso:** la WordPress installata in questo ambiente è la **7.1.2**, e `7.0`
è un valore legittimo e anzi inferiore alla versione richiesta, il che è corretto per un plugin che dichiara
compatibilità.

**Licenza — difetto reale, non citato nella versione precedente.** L'header del plugin dichiara
`License: GPLv2 or later` con `License URI: http://www.gnu.org/licenses/gpl-2.0.txt`, mentre il `composer.json`
del plugin dichiara `"license": "GPL-3.0+"`, e la documentazione del framework `omega-wp` rimanda a
`https://www.gnu.org/licenses/gpl-3.0-standalone.html` con etichetta «GPL V3.0+». Le due licenze **non sono
compatibili** per il riuso del codice: il header del plugin è GPLv2, il resto del progetto GPLv3. Va
allineata, e la divergenza va sanata prima di distribuire.

**Il `README.md` da 12 byte appartiene al framework, non al plugin.** Il `README.md` del framework
(`omega-wp/README.md`) è di 12 byte e una sola riga: per un pacchetto distribuito è una lacuna. Il `README.md`
del plugin è invece sostanzioso (3862 byte, 152 righe). Una versione precedente di questo report elencava il
README da 12 byte fra le osservazioni sul plugin: l'attribuzione era imprecisa, il file è del framework.

---

*Audit eseguito il 2026-10-04. Tutte le verifiche riportate sono state eseguite in prima persona con gli
strumenti indicati. I 5 rilievi risultati falsi nella versione precedente di questo documento sono stati
corretti; le correzioni sono elencate in §5 e in §8.*
