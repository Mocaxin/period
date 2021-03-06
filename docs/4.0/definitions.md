---
layout: default
title: Concepts and arguments
---

# Definitions

## Concepts

<p class="message-info">Since <code>version 4.4</code> all basic boundary types are supported by the library.</p>

- **interval** - `Period` is a PHP implementation of a datetime interval which consists of:
	- two datepoints;
	- the duration between them;
	- a boundary type. 


- **datepoint** - A position in time expressed as a `DateTimeImmutable` object. The starting datepoint is always less than or equal to the ending datepoint.

- **duration** - The continuous portion of time between two datepoints expressed as a `DateInterval` object. The duration cannot be negative.

- **boundary type** - An included datepoint means that the boundary datepoint itself is included in the interval as well, while an excluded datepoint means that the boundary datepoint is not included in the interval.  
The package supports included and excluded datepoint, thus, the following boundary types are supported:
	- included starting datepoint and excluded ending datepoint: `[start, end)`;
	- included starting datepoint and included ending datepoint : `[start, end]`;
	- excluded starting datepoint and included ending datepoint : `(start, end]`;
	- excluded starting datepoint and excluded ending datepoint : `(start, end)`;

<p class="message-warning">infinite or unbounded intervals are not supported.</p>

## Arguments

Since this package relies heavily on `DateTimeImmutable` and `DateInterval` objects and because it is sometimes complicated to get your hands on such objects the package comes bundled with:

- Two classes:
	- [League\Period\Datepoint](/4.0/datepoint/);
	- [League\Period\Duration](/4.0/duration/);

- Two simple functions defined under the `League\Period` namespace:
	- [League\Period\datepoint](/4.0/functions/);
	- [League\Period\duration](/4.0/functions/);

<p class="message-warning">Since <code>version 4.2</code> both functions are deprecated and you are encouraged to use the classes instead.</p>