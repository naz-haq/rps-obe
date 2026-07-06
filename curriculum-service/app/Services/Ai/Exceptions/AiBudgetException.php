<?php

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/**
 * Dilempar saat anggaran (kuota biaya) AI_KREDENSIAL tenant sudah terlampaui.
 * Controller dapat menangkap ini untuk merespons 402/429.
 */
class AiBudgetException extends RuntimeException {}
