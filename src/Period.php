<?php

/**
 * League.Period (https://period.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/period/blob/master/LICENSE (MIT License)
 * @version 4.0.0
 * @link    https://github.com/thephpleague/period
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Period;

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Generator;
use JsonSerializable;
use TypeError;
use const FILTER_VALIDATE_INT;
use function array_reduce;
use function array_unshift;
use function filter_var;
use function get_class;
use function gettype;
use function intdiv;
use function is_object;
use function is_string;
use function sprintf;

/**
 * A immutable value object class to manipulate Time Range.
 *
 * @package League.period
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @since   1.0.0
 */
final class Period implements PeriodInterface, JsonSerializable
{
    private const ISO8601_FORMAT = 'Y-m-d\TH:i:s.u\Z';

    /**
     * Period starting included datepoint.
     *
     * @var DateTimeImmutable
     */
    private $startDate;

    /**
     * Period ending excluded datepoint.
     *
     * @var DateTimeImmutable
     */
    private $endDate;

    /**
     * @inheritdoc
     */
    public static function __set_state(array $period)
    {
        return new self($period['startDate'], $period['endDate']);
    }

    /**
     * Creates a new instance.
     *
     * @param DateTimeInterface|string $startDate the starting included datepoint
     * @param DateTimeInterface|string $endDate   the ending excluded datepoint
     *
     * @throws Exception If $startDate is greater than $endDate
     */
    public function __construct($startDate, $endDate)
    {
        $startDate = self::filterDatePoint($startDate);
        $endDate = self::filterDatePoint($endDate);
        if ($startDate > $endDate) {
            throw new Exception('The ending datepoint must be greater or equal to the starting datepoint');
        }
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Validates the datepoint.
     *
     * @param DateTimeInterface|string $datepoint
     */
    private static function filterDatePoint($datepoint): DateTimeImmutable
    {
        if ($datepoint instanceof DateTimeImmutable) {
            return $datepoint;
        }

        if ($datepoint instanceof DateTime) {
            return DateTimeImmutable::createFromMutable($datepoint);
        }

        if (is_string($datepoint)) {
            return new DateTimeImmutable($datepoint);
        }

        throw new TypeError(sprintf(
            'The datepoint must a string or a DateTimeInteface object %s given',
            is_object($datepoint) ? get_class($datepoint) : gettype($datepoint)
        ));
    }

    /**
     * Validates the duration.
     *
     * The interval can be
     * <ul>
     * <li>a DateInterval object</li>
     * <li>an int interpreted as the duration expressed in seconds.</li>
     * <li>a string in a format supported by DateInterval::createFromDateString</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval
     */
    private static function filterDateInterval($interval): DateInterval
    {
        if ($interval instanceof DateInterval) {
            return $interval;
        }

        if (false !== ($res = filter_var($interval, FILTER_VALIDATE_INT))) {
            return new DateInterval('PT'.$res.'S');
        }

        if (is_string($interval)) {
            return DateInterval::createFromDateString($interval);
        }

        throw new TypeError(sprintf(
            'The interval must an integer, a string or a DateInterval object %s given',
            is_object($interval) ? get_class($interval) : gettype($interval)
        ));
    }

    /**
     * Filters the input integer.
     *
     * @param int|string|null $value
     *
     * @throws Exception if the given value can not be converted to an int.
     */
    private static function filterInt($value, string $name): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        if (false !== $int) {
            return $int;
        }

        throw new Exception(sprintf('The %s value must be an integer %s given', $name, gettype($value)));
    }

    /**
     * Filters a integer according to a range.
     *
     * @param int $value the value to validate
     * @param int $min   the minimum value
     * @param int $max   the maximal value
     *
     * @throws Exception If the value is not in the range
     */
    private static function filterRange(int $value, int $min, int $max): int
    {
        if ($value >= $min && $value <= $max) {
            return $value;
        }

        throw new Exception('The submitted value is not contained within a valid range');
    }

    /**
     * Creates new instance from a DatePeriod.
     *
     * @throws Exception If the submitted DatePeriod lacks an end datepoint.
     *                   This is possible if the DatePeriod was created using
     *                   recurrences instead of a end datepoint.
     *                   https://secure.php.net/manual/en/dateperiod.getenddate.php
     */
    public static function createFromDatePeriod(DatePeriod $datePeriod): self
    {
        $endDate = $datePeriod->getEndDate();
        if ($endDate instanceof DateTimeInterface) {
            return new self($datePeriod->getStartDate(), $endDate);
        }

        throw new Exception('The submitted DatePeriod object does not contain an end date');
    }

    /**
     * Creates new instance from a starting point and an interval.
     *
     * @param DateTimeInterface|string $startDate
     * @param DateInterval|int|string  $interval
     */
    public static function createFromDuration($startDate, $interval): self
    {
        $startDate = self::filterDatePoint($startDate);

        return new self($startDate, $startDate->add(self::filterDateInterval($interval)));
    }

    /**
     * Creates new instance from a ending excluded datepoint and an interval.
     *
     * @param DateTimeInterface|string $endDate
     * @param DateInterval|int|string  $interval
     */
    public static function createFromDurationBeforeEnd($endDate, $interval): self
    {
        $endDate = self::filterDatePoint($endDate);

        return new self($endDate->sub(self::filterDateInterval($interval)), $endDate);
    }

    /**
     * Creates new instance for a specific year.
     *
     * @param DateTimeInterface|string|int $int_or_datepoint a year as an int or a datepoint
     */
    public static function createFromYear($int_or_datepoint): self
    {
        if (is_int($int_or_datepoint)) {
            $startDate = (new DateTimeImmutable())->setDate($int_or_datepoint, 1, 1)->setTime(0, 0, 0, 0);

            return new self($startDate, $startDate->add(new DateInterval('P1Y')));
        }

        $datepoint = self::filterDatePoint($int_or_datepoint);
        $startDate = $datepoint->setDate((int) $datepoint->format('Y'), 1, 1)->setTime(0, 0, 0, 0);

        return new self($startDate, $startDate->add(new DateInterval('P1Y')));
    }

    /**
     * Creates new instance for a specific semester in a given year.
     *
     * @param DateTimeInterface|string|int $int_or_datepoint a year as an int or a datepoint
     * @param string|int|null              $semester         a semester index from 1 to 2 included
     */
    public static function createFromSemester($int_or_datepoint, $semester = null): self
    {
        if (is_int($int_or_datepoint)) {
            $month = ((self::filterRange(self::filterInt($semester, 'semester'), 1, 2) - 1) * 6) + 1;
            $startDate = (new DateTimeImmutable())
                ->setDate(self::filterInt($int_or_datepoint, 'year'), $month, 1)
                ->setTime(0, 0, 0, 0);

            return new self($startDate, $startDate->add(new DateInterval('P6M')));
        }

        $datepoint = self::filterDatePoint($int_or_datepoint);
        $month = (intdiv((int) $datepoint->format('n'), 6) * 6) + 1;
        $startDate = $datepoint
            ->setDate((int) $datepoint->format('Y'), $month, 1)
            ->setTime(0, 0, 0, 0);

        return new self($startDate, $startDate->add(new DateInterval('P6M')));
    }

    /**
     * Creates new instance for a specific quarter in a given year.
     *
     * @param DateTimeInterface|string|int $int_or_datepoint a year as an int or a datepoint
     * @param string|int|null              $quarter          quarter index from 1 to 4 included
     */
    public static function createFromQuarter($int_or_datepoint, $quarter = null): self
    {
        if (is_int($int_or_datepoint)) {
            $month = ((self::filterRange(self::filterInt($quarter, 'quarter'), 1, 4) - 1) * 3) + 1;
            $startDate = (new DateTimeImmutable())
                ->setDate(self::filterInt($int_or_datepoint, 'year'), $month, 1)
                ->setTime(0, 0, 0, 0);

            return new self($startDate, $startDate->add(new DateInterval('P3M')));
        }

        $datepoint = self::filterDatePoint($int_or_datepoint);
        $month = (intdiv((int) $datepoint->format('n'), 3) * 3) + 1;
        $startDate = $datepoint
            ->setDate((int) $datepoint->format('Y'), $month, 1)
            ->setTime(0, 0, 0, 0);

        return new self($startDate, $startDate->add(new DateInterval('P3M')));
    }

    /**
     * Creates new instance for a specific year and month.
     *
     * @param DateTimeInterface|string|int $int_or_datepoint a year as an int or a datepoint
     * @param string|int|null              $month            month index from 1 to 12 included
     */
    public static function createFromMonth($int_or_datepoint, $month = null): self
    {
        if (is_int($int_or_datepoint)) {
            $month = self::filterRange(self::filterInt($month, 'month'), 1, 12);
            $startDate = (new DateTimeImmutable())
                ->setDate(self::filterInt($int_or_datepoint, 'year'), $month, 1)
                ->setTime(0, 0, 0, 0);

            return new self($startDate, $startDate->add(new DateInterval('P1M')));
        }

        $datepoint = self::filterDatePoint($int_or_datepoint);
        $startDate = $datepoint
            ->setDate((int) $datepoint->format('Y'), (int) $datepoint->format('n'), 1)
            ->setTime(0, 0, 0, 0);

        return new self($startDate, $startDate->add(new DateInterval('P1M')));
    }

    /**
     * Creates new instance for a specific week.
     *
     * @param DateTimeInterface|string|int $int_or_datepoint a year as an int or a datepoint
     * @param string|int|null              $week             index from 1 to 53 included
     */
    public static function createFromWeek($int_or_datepoint, $week = null): self
    {
        if (is_int($int_or_datepoint)) {
            $week = self::filterRange(self::filterInt($week, 'week'), 1, 53);
            $startDate = (new DateTimeImmutable())
                ->setISODate(self::filterInt($int_or_datepoint, 'year'), $week)
                ->setTime(0, 0, 0, 0);

            return new self($startDate, $startDate->add(new DateInterval('P1W')));
        }

        $datepoint = self::filterDatePoint($int_or_datepoint);
        $startDate = $datepoint
            ->sub(new DateInterval('P'.((int) $datepoint->format('N') - 1).'D'))
            ->setTime(0, 0, 0, 0);

        return new self($startDate, $startDate->add(new DateInterval('P1W')));
    }

    /**
     * Creates new instance for a specific date.
     *
     * The date is truncated so that the time range starts at midnight
     * according to the date timezone and last a full day.
     *
     * @param DateTimeInterface|string $datepoint
     */
    public static function createFromDay($datepoint): self
    {
        $startDate = self::filterDatePoint($datepoint)->setTime(0, 0, 0, 0);

        return new self($startDate, $startDate->add(new DateInterval('P1D')));
    }

    /**
     * Creates new instance for a specific date and hour.
     *
     * The starting datepoint represents the beginning of the hour
     * The interval is equal to 1 hour
     *
     * @param DateTimeInterface|string $datepoint
     */
    public static function createFromHour($datepoint): self
    {
        $datepoint = self::filterDatePoint($datepoint);
        $startDate = $datepoint->setTime((int) $datepoint->format('H'), 0, 0, 0);

        return new self($startDate, $startDate->add(new DateInterval('PT1H')));
    }

    /**
     * Creates new instance for a specific date, hour and minute.
     *
     * The starting datepoint represents the beginning of the minute
     * The interval is equal to 1 minute
     *
     * @param DateTimeInterface|string $datepoint
     */
    public static function createFromMinute($datepoint): self
    {
        $datepoint = self::filterDatePoint($datepoint);
        $startDate = $datepoint->setTime((int) $datepoint->format('H'), (int) $datepoint->format('i'), 0, 0);

        return new self($startDate, $startDate->add(new DateInterval('PT1M')));
    }

    /**
     * Creates new instance for a specific date, hour, minute and second.
     *
     * The starting datepoint represents the beginning of the second
     * The interval is equal to 1 second
     *
     * @param DateTimeInterface|string $datepoint
     */
    public static function createFromSecond($datepoint): self
    {
        $datepoint = self::filterDatePoint($datepoint);
        $startDate = $datepoint->setTime(
            (int) $datepoint->format('H'),
            (int) $datepoint->format('i'),
            (int) $datepoint->format('s'),
            0
        );

        return new self($startDate, $startDate->add(new DateInterval('PT1S')));
    }

    /**
     * {@inheritdoc}
     */
    public function getStartDate(): DateTimeImmutable
    {
        return $this->startDate;
    }

    /**
     * {@inheritdoc}
     */
    public function getEndDate(): DateTimeImmutable
    {
        return $this->endDate;
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestampInterval(): float
    {
        return $this->endDate->getTimestamp() - $this->startDate->getTimestamp();
    }

    /**
     * {@inheritdoc}
     */
    public function getDateInterval(): DateInterval
    {
        return $this->startDate->diff($this->endDate);
    }

    /**
     * Allows iteration over a set of dates and times,
     * recurring at regular intervals, over the instance.
     *
     * This method is not part of the PeriodInterface.
     *
     * @see http://php.net/manual/en/dateperiod.construct.php
     *
     * @param DateInterval|int|string $interval
     */
    public function getDatePeriod($interval, int $option = 0): DatePeriod
    {
        return new DatePeriod($this->startDate, self::filterDateInterval($interval), $this->endDate, $option);
    }

    /**
     * Returns the string representation as a ISO8601 interval format.
     *
     * This method is not part of the PeriodInterface.
     *
     * @see https://en.wikipedia.org/wiki/ISO_8601#Time_intervals
     *
     * @return string
     */
    public function __toString()
    {
        $period = $this->jsonSerialize();

        return $period['startDate'].'/'.$period['endDate'];
    }

    /**
     * Returns the Json representation of an instance using
     * the JSON representation of dates as returned by Javascript Date.toJSON() method.
     *
     * This method is not part of the PeriodInterface.
     *
     * @see https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Date/toJSON
     *
     * @return string[]
     */
    public function jsonSerialize()
    {
        static $utc;
        $utc = $utc ?? new DateTimeZone('UTC');

        return [
            'startDate' => $this->startDate->setTimezone($utc)->format(self::ISO8601_FORMAT),
            'endDate' => $this->endDate->setTimezone($utc)->format(self::ISO8601_FORMAT),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function compareDuration(PeriodInterface $period): int
    {
        return $this->endDate <=> $this->startDate->add($period->getDateInterval());
    }

    /**
     * Tells whether the current instance duration is greater than the submitted one.
     *
     * This method is not part of the PeriodInterface.
     */
    public function durationGreaterThan(PeriodInterface $period): bool
    {
        return 1 === $this->compareDuration($period);
    }

    /**
     * Tells whether the current instance duration is less than the submitted one.
     *
     * This method is not part of the PeriodInterface.
     */
    public function durationLessThan(PeriodInterface $period): bool
    {
        return -1 === $this->compareDuration($period);
    }

    /**
     * Tells whether the current instance duration is equal to the submitted one.
     *
     * This method is not part of the PeriodInterface.
     */
    public function sameDurationAs(PeriodInterface $period): bool
    {
        return 0 === $this->compareDuration($period);
    }

    /**
     * {@inheritdoc}
     */
    public function equalsTo(PeriodInterface $period): bool
    {
        return $this->startDate == $period->getStartDate()
            && $this->endDate == $period->getEndDate();
    }

    /**
     * {@inheritdoc}
     */
    public function abuts(PeriodInterface $period): bool
    {
        return $this->startDate == $period->getEndDate()
            || $this->endDate == $period->getStartDate();
    }

    /**
     * {@inheritdoc}
     */
    public function overlaps(PeriodInterface $period): bool
    {
        return $this->startDate < $period->getEndDate()
            && $this->endDate > $period->getStartDate();
    }

    /**
     * {@inheritdoc}
     */
    public function isAfter($index): bool
    {
        if ($index instanceof PeriodInterface) {
            return $this->startDate >= $index->getEndDate();
        }

        return $this->startDate > self::filterDatePoint($index);
    }

    /**
     * {@inheritdoc}
     */
    public function isBefore($index): bool
    {
        if ($index instanceof PeriodInterface) {
            return $this->endDate <= $index->getStartDate();
        }

        return $this->endDate <= self::filterDatePoint($index);
    }

    /**
     * {@inheritdoc}
     */
    public function contains($index): bool
    {
        if ($index instanceof PeriodInterface) {
            return $this->containsPeriod($index);
        }

        return $this->containsDatePoint(self::filterDatePoint($index));
    }

    /**
     * Tells whether the a PeriodInterface is fully contained within the current instance.
     */
    private function containsPeriod(PeriodInterface $period): bool
    {
        return $this->containsDatePoint($period->getStartDate())
            && ($period->getEndDate() >= $this->startDate && $period->getEndDate() <= $this->endDate);
    }

    /**
     * Tells whether a datepoint is fully contained within the current instance.
     */
    private function containsDatePoint(DateTimeInterface $datepoint): bool
    {
        return ($datepoint >= $this->startDate && $datepoint < $this->endDate)
            || ($datepoint == $this->startDate && $datepoint == $this->endDate);
    }

    /**
     * Allows splitting an instance in smaller Period objects according to a given interval.
     *
     * This method is not part of the PeriodInterface.
     *
     * The returned iterable PeriodInterface set is ordered so that:
     * <ul>
     * <li>The first returned object MUST share the starting datepoint of the parent object.</li>
     * <li>The last returned object MUST share the ending datepoint of the parent object.</li>
     * <li>The last returned object MUST have a duration equal or lesser than the submitted interval.</li>
     * <li>All returned objects except for the first one MUST start immediately after the previously returned object</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval
     *
     * @return Generator|PeriodInterface[]
     */
    public function split($interval): Generator
    {
        $startDate = $this->startDate;
        $interval = self::filterDateInterval($interval);
        do {
            $endDate = $startDate->add($interval);
            if ($endDate > $this->endDate) {
                $endDate = $this->endDate;
            }
            yield new self($startDate, $endDate);

            $startDate = $endDate;
        } while ($startDate < $this->endDate);
    }

    /**
     * Allows splitting an instance in smaller Period objects according to a given interval.
     *
     * This method is not part of the PeriodInterface.
     *
     * The returned iterable PeriodInterface set is ordered so that:
     * <ul>
     * <li>The first returned object MUST share the ending datepoint of the parent object.</li>
     * <li>The last returned object MUST share the starting datepoint of the parent object.</li>
     * <li>The last returned object MUST have a duration equal or lesser than the submitted interval.</li>
     * <li>All returned objects except for the first one MUST end immediately before the previously returned object</li>
     * </ul>
     *
     * @param DateInterval|int|string $interval
     *
     * @return Generator|PeriodInterface[]
     */
    public function splitBackwards($interval): Generator
    {
        $endDate = $this->endDate;
        $interval = self::filterDateInterval($interval);
        do {
            $startDate = $endDate->sub($interval);
            if ($startDate < $this->startDate) {
                $startDate = $this->startDate;
            }
            yield new self($startDate, $endDate);

            $endDate = $startDate;
        } while ($endDate > $this->startDate);
    }

    /**
     * {@inheritdoc}
     */
    public function intersect(PeriodInterface $period): PeriodInterface
    {
        if (!$this->overlaps($period)) {
            throw new Exception(sprintf('Both %s objects should overlaps', PeriodInterface::class));
        }

        return new self(
            ($period->getStartDate() > $this->startDate) ? $period->getStartDate() : $this->startDate,
            ($period->getEndDate() < $this->endDate) ? $period->getEndDate() : $this->endDate
        );
    }

    /**
     * {@inheritdoc}
     */
    public function gap(PeriodInterface $period): PeriodInterface
    {
        if ($this->overlaps($period)) {
            throw new Exception(sprintf('Both %s objects should not overlaps', PeriodInterface::class));
        }

        if ($period->getStartDate() > $this->startDate) {
            return new self($this->endDate, $period->getStartDate());
        }

        return new self($period->getEndDate(), $this->startDate);
    }

    /**
     * Computes the difference between two overlapsing PeriodInterface objects.
     *
     * This method is not part of the PeriodInterface.
     *
     * Returns an array containing the difference expressed as Period objects
     * The array will:
     *
     * <ul>
     * <li>be empty if both objects have the same datepoints</li>
     * <li>contain one Period object if both objects share one datepoint</li>
     * <li>contain two Period objects if both objects share no datepoint</li>
     * </ul>
     *
     * @throws Exception if both objects do not overlaps
     */
    public function diff(PeriodInterface $period): array
    {
        if ($period->equalsTo($this)) {
            return [];
        }

        $intersect = $this->intersect($period);
        $merge = $this->merge($period);
        if ($merge->getStartDate() == $intersect->getStartDate()) {
            return [$merge->startingOn($intersect->getEndDate())];
        }

        if ($merge->getEndDate() == $intersect->getEndDate()) {
            return [$merge->endingOn($intersect->getStartDate())];
        }

        return [
            $merge->endingOn($intersect->getStartDate()),
            $merge->startingOn($intersect->getEndDate()),
        ];
    }

    /**
     * @inheritdoc
     *
     * @param DateTimeInterface|string $datepoint
     */
    public function startingOn($datepoint): PeriodInterface
    {
        $startDate = self::filterDatePoint($datepoint);
        if ($startDate == $this->startDate) {
            return $this;
        }

        return new self($startDate, $this->endDate);
    }

    /**
     * @inheritdoc
     *
     * @param DateTimeInterface|string $datepoint
     */
    public function endingOn($datepoint): PeriodInterface
    {
        $endDate = self::filterDatePoint($datepoint);
        if ($endDate == $this->endDate) {
            return $this;
        }

        return new self($this->startDate, $endDate);
    }

    /**
     * Returns a new instance with a new ending datepoint.
     *
     * This method is not part of the PeriodInterface.
     *
     * @param DateInterval|int|string $interval
     */
    public function withDuration($interval): PeriodInterface
    {
        return $this->endingOn($this->startDate->add(self::filterDateInterval($interval)));
    }

    /**
     * Returns a new instance with a new starting datepoint.
     *
     * This method is not part of the PeriodInterface.
     *
     * @param DateInterval|int|string $interval
     */
    public function withDurationBeforeEnd($interval): PeriodInterface
    {
        return $this->startingOn($this->endDate->sub(self::filterDateInterval($interval)));
    }

    /**
     * Returns a new instance with a new starting datepoint
     * moved forward or backward by the given interval.
     *
     * This method is not part of the PeriodInterface.
     *
     * @param DateInterval|int|string $interval
     */
    public function moveStartDate($interval): PeriodInterface
    {
        return $this->startingOn($this->startDate->add(self::filterDateInterval($interval)));
    }

    /**
     * Returns a new instance with a new ending datepoint
     * moved forward or backward by the given interval.
     *
     * This method is not part of the PeriodInterface.
     *
     * @param DateInterval|int|string $interval
     */
    public function moveEndDate($interval): PeriodInterface
    {
        return $this->endingOn($this->endDate->add(self::filterDateInterval($interval)));
    }

    /**
     * Returns a new instance where the datepoints
     * are moved forwards or backward simultaneously by the given DateInterval.
     *
     * This method is not part of the PeriodInterface.
     *
     * @param DateInterval|int|string $interval
     */
    public function move($interval): self
    {
        $interval = self::filterDateInterval($interval);
        $period = new self($this->startDate->add($interval), $this->endDate->add($interval));
        if ($period->equalsTo($this)) {
            return $this;
        }

        return $period;
    }

    /**
     * Returns a new instance where the given interval is
     * substracted from the starting datepoint and added to the ending datepoint.
     * Depending on the interval value, the resulting instance duration
     * will be expanded or shrinked.
     *
     * This method is not part of the PeriodInterface.
     *
     * @param DateInterval|int|string $interval
     */
    public function expand($interval): self
    {
        $interval = self::filterDateInterval($interval);
        $period = new self($this->startDate->sub($interval), $this->endDate->add($interval));
        if ($period->equalsTo($this)) {
            return $this;
        }

        return $period;
    }

    /**
     * Returns the difference between two PeriodInterface objects expressed in seconds.
     *
     * This method is not part of the PeriodInterface.
     */
    public function timestampIntervalDiff(PeriodInterface $period): float
    {
        return $this->getTimestampInterval() - $period->getTimestampInterval();
    }

    /**
     * Returns the difference between two PeriodInterface objects expressed in DateInterval.
     *
     * This method is not part of the PeriodInterface.
     */
    public function dateIntervalDiff(PeriodInterface $period): DateInterval
    {
        return $this->endDate->diff($this->startDate->add($period->getDateInterval()));
    }

    /**
     * Merges one or more PeriodInterface objects to return a new instance.
     * The resulting instance represents the largest duration possible.
     *
     * This method is not part of the PeriodInterface.
     *
     * @param PeriodInterface ...$periods
     */
    public function merge(PeriodInterface $period, PeriodInterface ...$periods): PeriodInterface
    {
        array_unshift($periods, $period);

        return array_reduce($periods, [$this, 'reducer'], $this);
    }

    /**
     * Returns new instance whose endpoints are the largest possible
     * between 2 instance of PeriodInterface objects.
     */
    private function reducer(PeriodInterface $carry, PeriodInterface $period): PeriodInterface
    {
        if ($carry->getStartDate() > $period->getStartDate()) {
            $carry = $carry->startingOn($period->getStartDate());
        }

        if ($carry->getEndDate() < $period->getEndDate()) {
            $carry = $carry->endingOn($period->getEndDate());
        }

        return $carry;
    }
}
