# dhbwma-lectureplantoical (LP-parser)

An application parsing the DHBW Mannheim (vorlesungsplan.dhbw-mannheim.de) HTML lecture plan, written in PHP.

Endpoints:

* `/<course>/ics` Returns a .ics (iCalendar) file containing the calendar for <course>
* `/<course>/json` Returns the Lectures for <course> as an list in json. For the format see [here](#Lecture-format)
* `/<course>/txt` Mainly useful for debugging, displays the internal data structure in a human-readable form.
* `/list` Returns the list of courses in a <course> to <uid> assignment. (Format is `{Object<string, number>}`)


### Lecture format
```
[{
    title: string,
    start: {date: string, timezone: string, timezone_type: number},
    end: {date: string, timezone: string, timezone_type: number},
    description: string|null,
    room: string|null,
    id: string
}]
```

## TODO
- Also make the endpoints accessible by uid.
- Properly handle all-day events