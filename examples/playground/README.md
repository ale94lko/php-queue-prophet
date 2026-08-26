# php-queue-prophet playground

Mini proyecto para probar la librería en local sin Laravel/Symfony.

## Requisitos

- PHP 8.1+
- Composer

## Instalación

Desde esta carpeta:

```bash
cd examples/playground
composer install
```

Composer enlaza el paquete padre (`ale94lko/php-queue-prophet`) vía `path` repository.

## Ejecutar

```bash
# Las 3 demos
composer demo

# Solo fuga de memoria → predicción de OOM
composer demo:memory

# Memoria estable → INF (sin riesgo)
composer demo:stable

# Time-to-Overflow de cola
composer demo:queue
```

También:

```bash
php bin/demo.php
php bin/demo.php memory
php bin/demo.php stable
php bin/demo.php queue
```

## Qué verás

| Demo | Qué demuestra |
|------|----------------|
| `memory` | Worker que “fuga” ~256 KB/job; el predictor marca `STOP` antes del límite |
| `stable` | Memoria plana → `remaining = INF` |
| `queue` | Varios escenarios de llegada vs procesamiento y TTO |

## Experimentar

Edita las clases en `src/`:

- `MemoryLeakDemo`: `$leakPerJobBytes`, `$stopBelowJobs`, límite de memoria
- `QueueOverflowDemo`: capacidad y tasas `in` / `out`

Luego vuelve a lanzar `composer demo`.
