[Back to documentation index](README.md)

# Search in the course shortcodes — columns and order of the results

The course shortcodes of local_musi show the booking options ("courses") of a booking instance.
With `search=1` they get a search box. This page explains **where** the search looks and
**in which order** the results are shown.

## Shortcodes

Everything on this page applies to these shortcodes:

| Shortcode | Shows |
|-----------|-------|
| `[allekurseliste]` | All courses as a list |
| `[allekursekarten]` | All courses as cards |
| `[allekursegrid]` | All courses as a grid |
| `[meinekursekarten]` | The booked courses of the user as cards |
| `[meinekurseliste]` | The booked courses of the user as a list |
| `[meinefavoriten]` | The favourites of the user |
| `[trainerkursekarten]` | The courses the user teaches |
| `[kontaktpersonkursekarten]` | The courses the user is the responsible contact for |

## Which columns are searched

`search=1` searches these columns:

| Column | Content |
|--------|---------|
| `titleprefix` | Course number |
| `text` | Name of the course |
| `sportsdivision` | Sports division (booking custom field) |
| `sport` | Sport (booking custom field) |
| `location` | Location |
| `teachernames` | Names of the teachers |
| `botags` | Tags (booking custom field) |

A search text with several words finds the courses that contain **every** word somewhere in these
columns. Upper and lower case do not matter.

Teachers are found by their **names** only: first name and last name, in any order
("Pia Berger", "Berger Pia" and "Berger, Pia" all work).

### Adding columns: `fulltextsearchcolumns`

```text
[allekurseliste search=1 fulltextsearchcolumns="keywords"]
```

`fulltextsearchcolumns` is a comma separated list of fields of the booking options and shortnames
of booking custom fields. The columns are searched **in addition** to the standard columns, they
never replace them. Unknown names are ignored. The argument shows the search box even without `search=1`.

A typical use is a custom field like `keywords` that is not displayed but holds search terms.

## Order of the search results

The results of a search are always ordered by **course number first, name second**. This is built
in, no argument is needed:

1. courses whose course number **equals** the search text,
2. courses whose course number **contains every word** of the search text,
3. courses whose name **equals** the search text,
4. courses whose name **contains every word** of the search text,
5. all other results, e.g. courses found by their sport, location or teachers.

Inside a group the sorting of the table applies.

### Example

A user searches for **424**:

| Group | Why | Example (course number – name) |
|-------|-----|--------------------------------|
| 1 | The course number is "424" | 424 – Pilates |
| 2 | The course number contains "424" | 4240 – Tanz |
| 3 | The name is "424" | A3 – 424 |
| 4 | The name contains "424" | A4 – Kurs 424 Yoga |
| 5 | All other results | A5 – Yoga (found by its keywords) |

### More priority columns: `priority`

```text
[allekurseliste search=1 fulltextsearchcolumns="keywords" priority="keywords"]
```

The columns of `priority` are added **after** course number and name. In the example the order is
course number, name, keywords: courses found by their keywords come before the courses found by
sport, location or teachers, but never before a hit in the course number or the name.

- Course number and name always come first. `priority="keywords,text,titleprefix"` gives the same
  order as `priority="keywords"`.
- A column in `priority` must also be searched: it has to be one of the standard columns or be
  added with `fulltextsearchcolumns`. The priority only orders what the search finds.
- The teachers are named `teachernames`; `teacher` and `teachers` are accepted as well, e.g.
  `priority="teacher"`.
- Unknown names are ignored.

### Booking instance setting "Priority of the search results"

mod_booking has a setting with this name in the booking instance. The course shortcodes of
local_musi do **not** use it: their order is always course number, name, then the columns of the
`priority` argument. The setting only affects the view of the booking instance itself and the
shortcodes of mod_booking.

## Requirements

- The search by teacher names and the names `teachernames`, `teacher` and `teachers` in `priority`
  need a mod_booking version newer than 10.0.8 (2026110602).

## Troubleshooting

| What you see | Reason |
|--------------|--------|
| A column of `priority` has no effect | The column is not searched. Add it to `fulltextsearchcolumns`. |
| A changed shortcode argument has no effect | The table of a shortcode is cached. Purge the caches. |
| Searching for a number lists unexpected courses | The number is part of another searched column. Such results are shown at the end. |
