<?php

namespace App\Services\Generator\Exceptions;

/**
 * Draf sudah berubah sejak base_revisi yang dikirim klien (optimistic locking).
 * Dipetakan ke HTTP 409 agar klien meninjau ulang diff terbaru sebelum apply.
 */
class RevisiConflictException extends GeneratorException {}
