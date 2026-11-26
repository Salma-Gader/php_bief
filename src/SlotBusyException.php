<?php
declare(strict_types=1);

/**
 * Thrown when another booking for the same sitting is taking too long,
 * so the slot lock could not be taken. The guest is asked to try again.
 */
final class SlotBusyException extends RuntimeException
{
}
