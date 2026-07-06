<?php

namespace App\Services\Generator\Exceptions;

use RuntimeException;

/**
 * Dilempar saat pelanggaran aturan pipeline generator: tahap tak dikenal,
 * prasyarat tahap belum disetujui, tahap terkunci, atau keluaran AI bukan
 * JSON valid.
 */
class GeneratorException extends RuntimeException {}
