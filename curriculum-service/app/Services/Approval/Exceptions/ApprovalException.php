<?php

namespace App\Services\Approval\Exceptions;

use RuntimeException;

/** Dilempar saat transisi status persetujuan RPS tidak diizinkan. */
class ApprovalException extends RuntimeException {}
