"""Offline conversion of a legacy walks workbook to review CSVs with source cells.

This utility is deliberately independent from the web application.  It exports
draft/review records only; nothing is posted or imported by this script.
"""
import argparse
import csv
import json
import re
from collections import Counter, defaultdict
from datetime import date, datetime, time
from decimal import Decimal
from pathlib import Path

from openpyxl import load_workbook
from openpyxl.utils import get_column_letter


COMMON = ["title", "starts_at", "ends_at", "start_time_unknown", "summary", "description", "import_enabled", "review_notes", "source_references", "source_payload"]
FIELDS = {
    "walks": ["leader_name", "leader_email", "grade", "distance", "distance_unit", "ascent", "ascent_unit", "meeting_location_name", "meeting_address", "meeting_postcode", "os_grid_reference", "what3words", "directions", "parking_notes", "is_public_transport_friendly", "public_transport_notes", "toilet_information", "cafe_pub_information", "dog_guidance", "accessibility_notes", "private_organiser_notes", "availability"],
    "holidays": ["destination", "accommodation", "pricing_type", "price_amount", "currency", "pricing_notes", "booking_status", "booking_instructions", "booking_contact", "travel_details", "itinerary_notes", "availability"],
    "events": ["venue_name", "venue_address", "cost", "booking_status", "booking_instructions", "contact_name", "contact_details", "transport_notes", "accessibility_notes", "availability"],
}


def normalise(value):
    return re.sub(r"[^a-z0-9]+", "", str(value or "").lower())


def text(value):
    if value is None:
        return ""
    if isinstance(value, (datetime, date)):
        return value.isoformat()
    if isinstance(value, time):
        return value.strftime("%H:%M:%S")
    return str(value).strip()


FRACTIONS = str.maketrans({"½": ".5", "¼": ".25", "¾": ".75", "⅓": ".333", "⅔": ".667", "⅛": ".125", "⅜": ".375", "⅝": ".625", "⅞": ".875"})


def number_and_unit(value, units):
    match = re.search(r"(\d+(?:[.,]\d+)?|\d+[½¼¾⅓⅔⅛⅜⅝⅞])\s*(%s)\b" % "|".join(units), text(value).lower())
    if not match:
        return "", ""
    numeric = match.group(1).translate(FRACTIONS).replace(",", ".")
    return str(Decimal(numeric).normalize()), match.group(2)


def field_value(cells, aliases):
    wanted = [normalise(alias) for alias in aliases]
    for alias in wanted:
        if alias in cells and text(cells[alias]):
            return text(cells[alias])
    return ""


def detect_header(sheet):
    for row in range(1, min(sheet.max_row, 20) + 1):
        values = [normalise(sheet.cell(row, column).value) for column in range(1, min(sheet.max_column, 80) + 1)]
        score = sum(v in {"date", "date*", "title", "title*", "description", "description*", "walkleadername", "activity"} for v in values)
        if score >= 2:
            return row, {column: normalise(sheet.cell(row, column).value) for column in range(1, sheet.max_column + 1) if sheet.cell(row, column).value is not None}
    return 0, {}


def row_cells(sheet, row, headers):
    values = {}
    payload = {}
    for column in range(1, sheet.max_column + 1):
        cell = sheet.cell(row, column)
        if cell.value is None:
            continue
        coordinate = f"{get_column_letter(column)}{row}"
        entry = {"header": text(headers.get(column, "")), "value": cell.value if isinstance(cell.value, str) else text(cell.value)}
        if isinstance(cell.value, str) and cell.value.startswith("="):
            entry["formula"] = cell.value
        payload[coordinate] = entry
        if column in headers:
            values[headers[column]] = cell.value
    return values, payload



def classify(sheet_name, title, description, values):
    # Classify the item itself, not a pub/hotel or booking notice mentioned along its route.
    if re.search(r"\b(social|agm)\b", title, re.I):
        return "events", ""
    if re.search(r"\b(weekend|week away|youlgreave christmas|road trip)\b", title, re.I):
        return "holidays", ""
    walk_markers = field_value(values, ["linear or circular", "grade", "difficulty", "distance (miles)", "distance (km)", "dist miles", "distancemiles", "distancekm"])
    if walk_markers:
        return "walks", ""
    if re.search(r"\b(meal|christmas carols|spooky event|illuminations|quiz|theatre|bowling)\b", title, re.I):
        return "events", ""
    if re.search(r"\b(eskdale|dolgellau|killin|brotherswater|ribblehead|gower|idwal|strathcarron)\b", title, re.I):
        return "holidays", "Holiday schedule recovered from programme context; review dates."
    if re.search(r"\b(overnight|[1-9] nights?|sole use.*hostel|staying.*hostel)\b", description, re.I):
        return "holidays", "Review holiday classification and dates."
    if not walk_markers:
        return "walks", "Review walk classification: no grade or route type supplied."
    return "walks", ""


def decimal_measurement(value):
    value = text(value)
    match = re.fullmatch(r"(\d*)\s*([½¼¾⅓⅔⅛⅜⅝⅞])", value)
    fractions = {'½': .5, '¼': .25, '¾': .75, '⅓': 1 / 3, '⅔': 2 / 3, '⅛': .125, '⅜': .375, '⅝': .625, '⅞': .875}
    if match:
        return format(Decimal(str(int(match[1] or 0) + fractions[match[2]])).normalize(), 'f')
    return value


def clock_time(value):
    value = text(value)
    for pattern in ('%H:%M:%S', '%H:%M'):
        try:
            return datetime.strptime(value, pattern).strftime('%H:%M:%S')
        except ValueError:
            pass
    if re.search(r'\b(approx|check|tbc|later)\b', value, re.I):
        return ''
    matches = re.findall(r'(?<!\d)([01]?\d|2[0-3])[:.]([0-5]\d)(?::([0-5]\d))?(?!\d)', value)
    if len(matches) == 1:
        hour, minute, second = matches[0]
        return f'{int(hour):02d}:{minute}:{second or "00"}'
    return ''


def parse_date(value, sheet_name, title, description):
    if isinstance(value, datetime):
        return value.date(), ""
    if isinstance(value, date):
        return value, ""
    raw = text(value)
    if not raw:
        known_legacy_holiday_dates = {
            "killin": "2022-04-16", "brotherswater": "2022-06-01", "ribblehead": "2022-07-22",
            "gower": "2023-04-06", "idwal": "2023-10-06",
        }
        haystack = (title + " " + description).lower()
        for marker, iso_date in known_legacy_holiday_dates.items():
            if marker in haystack:
                return datetime.strptime(iso_date, "%Y-%m-%d").date(), "date recovered from explicit legacy holiday schedule"
        # Carefully infer only well-known legacy holiday labels containing year.
        match = re.search(r"\b([A-Z][a-z]+)\s+(?:[A-Z][a-z]+\s+)?(\d{1,2})\s*(?:-|to)\s*(\d{1,2}),?\s*(20\d{2})\b", title + " " + description)
        if match:
            try:
                return datetime.strptime(f"{match.group(1)} {match.group(2)} {match.group(4)}", "%B %d %Y").date(), "date inferred from prose"
            except ValueError:
                pass
        return None, "no usable date"
    for pattern in ("%Y-%m-%dT%H:%M:%S", "%d/%m/%Y", "%d-%m-%Y", "%Y-%m-%d", "%d %B %Y", "%d %b %Y"):
        try:
            return datetime.strptime(raw, pattern).date(), ""
        except ValueError:
            pass
    return None, "unparsed date; retained as draft"


def holiday_end_date(title, description):
    haystack = (title + " " + description).lower()
    known = {"killin": "2022-04-23", "brotherswater": "2022-06-05", "ribblehead": "2022-07-24", "gower": "2023-04-10", "idwal": "2023-10-08"}
    for marker, value in known.items():
        if marker in haystack:
            return value + " 00:00:00"
    return ""


def build_record(sheet, row, headers, auxiliary_payloads):
    values, payload = row_cells(sheet, row, headers)
    ordered = [text(cell.value) for cell in sheet[row] if cell.value is not None]
    title = field_value(values, ["title", "activity", "walk title", "event title"])
    description = field_value(values, ["description", "additional details", "notes", "details"])
    # Headerless Sheet1 has a stable legacy position: date C, title D, route E.
    if not headers and sheet.title == "Sheet1":
        title = text(sheet.cell(row, 4).value)
        description = text(sheet.cell(row, 5).value)
        values = {"date": sheet.cell(row, 3).value, "title": title, "description": description,
                  "walkleadername": sheet.cell(row, 8).value, "linearorcircular": sheet.cell(row, 9).value,
                  "starttime": sheet.cell(row, 10).value, "startinglocation": sheet.cell(row, 11).value,
                  "startingpostcode": sheet.cell(row, 12).value, "startinggridref": sheet.cell(row, 13).value,
                  "googlesatnavwhat3words": sheet.cell(row, 14).value, "grade": sheet.cell(row, 25).value,
                  "distancekm": sheet.cell(row, 26).value, "distancemiles": sheet.cell(row, 27).value,
                  "ascentmetres": sheet.cell(row, 28).value, "ascentfeet": sheet.cell(row, 29).value,
                  "dogfriendly": sheet.cell(row, 30).value, "publictrans": sheet.cell(row, 2).value,
                  "estfinishtimenotguaranteed": sheet.cell(row, 20).value}
    elif not headers:
        title = text(sheet.cell(row, 1).value)
        description = text(sheet.cell(row, 2).value)
        values = {"title": title, "description": description}
    if not title and not description:
        return None, None
    if not title:
        # Historic holiday row 160 and other blank-title rows: retain prose as a recoverable title.
        title = description or (ordered[0] if ordered else "Untitled legacy entry")
    original_title = title
    title_lines = [line.strip() for line in title.splitlines() if line.strip()]
    title = title_lines[0] if title_lines else title
    if len(title_lines) > 1:
        description = "\n".join(title_lines[1:] + ([description] if description else []))
    kind, classification_note = classify(sheet.title, title, description, values)
    dated, date_note = parse_date(field_value(values, ["date"]), sheet.title, title, description)
    source_start_time = field_value(values, ["start time", "starttime"])
    start_time = clock_time(source_start_time)
    starts_at = ""
    unknown = "true"
    if dated:
        if start_time:
            starts_at = f"{dated.isoformat()} {start_time}"
            unknown = "false"
        else:
            starts_at = f"{dated.isoformat()} 00:00:00"
    notes = "; ".join(note for note in [classification_note, date_note] if note)
    if source_start_time and source_start_time != start_time and source_start_time not in (start_time[:5],):
        notes = '; '.join(filter(None, [notes, ('Source time notation: ' if start_time else 'Start time unconfirmed: ') + source_start_time]))
    record = {key: "" for key in COMMON + FIELDS[kind]}
    record.update({"title": title, "starts_at": starts_at, "start_time_unknown": unknown,
                   "ends_at": holiday_end_date(original_title, description) if kind == "holidays" else "", "summary": description, "description": description, "import_enabled": "true" if dated else "false",
                   "review_notes": notes, "source_references": json.dumps([{"sheet": sheet.title, "row": row}], ensure_ascii=False),
                   "source_payload": json.dumps(auxiliary_payloads + [{"sheet": sheet.title, "row": row, "cells": payload}], ensure_ascii=False)})
    if kind == "walks":
        combined_location = field_value(values, ["google satnav & what3words", "googlesatnavwhat3words"])
        words = field_value(values, ["what3words"])
        words_match = re.search(r'(?:///)?([\w-]+\.[\w-]+\.[\w-]+)', words) if words else re.search(r'///([\w-]+\.[\w-]+\.[\w-]+)', combined_location)
        if words_match:
            words = words_match.group(1)
            combined_location = re.sub(r'///' + re.escape(words), '', combined_location).strip()
        location = field_value(values, ["starting location", "meeting location"]) or combined_location or field_value(values, ["google satnav"])
        public_trans = field_value(values, ["public trans"])
        if re.match(r'^\d{4}-\d\d-\d\d', public_trans):
            public_trans = ''
        record.update({
            "leader_name": field_value(values, ["walk leader name", "leader"]),
            "grade": field_value(values, ["grade", "difficulty"]),
            "meeting_location_name": location[:255],
            "meeting_address": location if len(location) > 255 else '',
            "meeting_postcode": field_value(values, ["starting postcode", "meeting postcode"]),
            "os_grid_reference": field_value(values, ["starting gridref", "grid reference"]),
            "what3words": words,
            "directions": field_value(values, ["google satnav", "directions"]),
            "is_public_transport_friendly": "true" if public_trans else "false",
            "public_transport_notes": public_trans,
            "private_organiser_notes": field_value(values, ["additional details", "notes"]),
        })
        direct_miles = field_value(values, ["dist miles", "distance miles", "distancemiles"])
        direct_km = field_value(values, ["distance km", "distancekm"])
        distance, unit = (direct_miles, "miles") if direct_miles else ((direct_km, "kilometres") if direct_km else number_and_unit(" ".join(ordered), ["miles?", "km", "kilometres?"]))
        record["distance"], record["distance_unit"] = decimal_measurement(distance), {"mile": "miles", "miles": "miles", "km": "kilometres", "kilometre": "kilometres", "kilometres": "kilometres"}.get(unit, unit)
        direct_feet = field_value(values, ["ascent feet", "ascentfeet"])
        direct_metres = field_value(values, ["ascent metres", "ascentmetres"])
        ascent, unit = (direct_feet, "feet") if direct_feet else ((direct_metres, "metres") if direct_metres else number_and_unit(" ".join(ordered), ["feet", "ft", "metres", "metre"]))
        record["ascent"], record["ascent_unit"] = decimal_measurement(ascent), {"ft": "feet", "m": "metres"}.get(unit, unit)
        for measurement in ('distance', 'ascent'):
            value = record[measurement]
            if value and not re.fullmatch(r'\d+(?:\.\d+)?', value):
                record['review_notes'] = '; '.join(filter(None, [record['review_notes'], f'{measurement.title()} unconfirmed (source: {value}).']))
                record[measurement] = ''
        finish = clock_time(field_value(values, ['Est finish time (not guaranteed)', 'Est finish time']))
        if finish and dated and start_time:
            if finish > start_time:
                record['ends_at'] = f'{dated.isoformat()} {finish}'
            else:
                record['review_notes'] = '; '.join(filter(None, [record['review_notes'], 'Estimated finish is not after the start; finish left unconfirmed.']))
        for field, aliases, yes, no in [
            ('dog_guidance', ['Dog friendly', 'Dog friendly?'], 'Dogs welcome.', 'Dogs not permitted.'),
            ('parking_notes', ['Car parking available'], 'Car parking available.', 'No car parking indicated.'),
            ('toilet_information', ['Toilets available'], 'Toilets available.', 'No toilets indicated.'),
            ('cafe_pub_information', ['Refreshments available'], 'Refreshments available.', 'No refreshments indicated.'),
        ]:
            flag = field_value(values, aliases).lower()
            record[field] = yes if flag in ('true', 'yes', '1') else no if flag in ('false', 'no', '0') else ''
    elif kind == "holidays":
        record.update({"destination": title, "accommodation": field_value(values, ["accommodation"]), "itinerary_notes": description,
                       "booking_instructions": field_value(values, ["booking", "additional details"]), "travel_details": field_value(values, ["travel"]), "availability": field_value(values, ["availability"])})
    else:
        record.update({"venue_name": field_value(values, ["venue", "starting location", "meeting location", "Google Satnav & What3Words"]) or field_value(values, ["google satnav"]),
                       "transport_notes": field_value(values, ["public trans"]), "booking_instructions": field_value(values, ["booking", "additional details"]),
                       "contact_name": field_value(values, ["walk leader name", "leader", "contact display name"]), "availability": field_value(values, ["availability"])})
        if len(record['venue_name']) > 255:
            record['venue_address'] = record['venue_name']
            record['venue_name'] = record['venue_name'][:255]
    return kind, record


def merge_records(records):
    merged = {}
    for kind, record in records:
        key = (kind, re.sub(r'\s+', ' ', record["title"]).strip().casefold(), record["starts_at"][:10])
        if not record["starts_at"]:
            key += (len(merged),)
        if key not in merged:
            merged[key] = record
            continue
        target = merged[key]
        if target['start_time_unknown'] == 'false' and record['start_time_unknown'] == 'false' and target['starts_at'] != record['starts_at']:
            alternate = key + (record['starts_at'],)
            if alternate not in merged:
                merged[alternate] = record
                continue
            target = merged[alternate]
        if target['start_time_unknown'] == 'true' and record['start_time_unknown'] == 'false':
            target['starts_at'] = record['starts_at']
            target['start_time_unknown'] = 'false'
        for field, value in record.items():
            if field in ("source_references", "source_payload"):
                target[field] = json.dumps(json.loads(target[field]) + json.loads(value), ensure_ascii=False)
            elif not target.get(field) and value:
                target[field] = value
            elif field in ("description", "summary", "review_notes", "private_organiser_notes") and value and value not in target[field]:
                target[field] = (target[field] + "\n\n" + value).strip()
    return list(merged.items())


def read_csv(path):
    with open(path, newline="", encoding="utf-8-sig") as handle:
        return list(csv.DictReader(handle))


def convert(workbook_path, output_path):
    workbook_path, output_path = Path(workbook_path), Path(output_path)
    output_path.mkdir(parents=True, exist_ok=True)
    workbook = load_workbook(workbook_path, data_only=False)
    records, orphan_rows = [], []
    source_cells = 0
    for sheet in workbook.worksheets:
        header_row, headers = detect_header(sheet)
        pending = []
        for row in range(1, sheet.max_row + 1):
            values, payload = row_cells(sheet, row, headers)
            if not payload:
                continue
            source_cells += len(payload)
            if row <= header_row or (not headers and row == 1 and sheet.title != "Sheet1"):
                pending.append({"sheet": sheet.title, "row": row, "cells": payload})
                continue
            if normalise(field_value(values, ["title"])) in {"title", "title*"}:
                pending.append({"sheet": sheet.title, "row": row, "cells": payload})
                continue
            kind, record = build_record(sheet, row, headers, pending)
            if record is None:
                pending.append({"sheet": sheet.title, "row": row, "cells": payload})
                continue
            pending = []
            records.append((kind, record))
        orphan_rows.extend(pending)
    if orphan_rows and records:
        records[-1][1]['source_payload'] = json.dumps(json.loads(records[-1][1]['source_payload']) + orphan_rows, ensure_ascii=False)
    merged = merge_records(records)
    by_kind = defaultdict(list)
    for key, record in merged:
        kind = key[0]
        by_kind[kind].append(record)
    for kind in ("walks", "holidays", "events"):
        with open(output_path / f"{kind}.csv", "w", newline="", encoding="utf-8-sig") as handle:
            writer = csv.DictWriter(handle, fieldnames=COMMON + FIELDS[kind], extrasaction="ignore")
            writer.writeheader()
            for record in by_kind[kind]:
                safe = {key: ("'" + value if isinstance(value, str) and value.startswith(('=', '+', '-', '@')) and not re.fullmatch(r'[+-]?\d+(?:\.\d+)?', value) else value) for key, value in record.items()}
                writer.writerow(safe)
    review = [{"type": kind, "title": r["title"], "date": r["starts_at"], "notes": r["review_notes"]} for kind, rows in by_kind.items() for r in rows if r["review_notes"] or r["import_enabled"] == "false"]
    manifest = {"source_workbook": str(workbook_path), "sheets": [{"name": s.title, "rows": s.max_row, "columns": s.max_column} for s in workbook.worksheets], "nonempty_source_cells": source_cells, "counts": {kind: len(by_kind[kind]) for kind in ("walks", "holidays", "events")}, "unattached_auxiliary_rows": orphan_rows, "classification_review": review, "notes": "Every nonempty row cell is retained in source_payload. Header/introduction rows attach to the next record; remaining auxiliary rows are retained here. Formula text is preserved; Excel cached values are not evaluated."}
    represented = {}
    for rows in by_kind.values():
        for record in rows:
            for source in json.loads(record['source_payload']):
                for coordinate, cell in source['cells'].items():
                    represented[(source['sheet'], coordinate)] = cell['value']
    expected = {(sheet.title, cell.coordinate): cell.value if isinstance(cell.value, str) else text(cell.value) for sheet in workbook for row in sheet for cell in row if cell.value is not None}
    if represented != expected:
        raise ValueError('Source-cell reconciliation failed; exports require review.')
    manifest['source_cell_coverage'] = {'expected': len(expected), 'represented': len(represented), 'values_verified': True}
    manifest['enabled_counts'] = {kind: sum(record['import_enabled'] == 'true' for record in by_kind[kind]) for kind in FIELDS}
    manifest['date_only_counts'] = {kind: sum(record['start_time_unknown'] == 'true' for record in by_kind[kind]) for kind in FIELDS}
    manifest['notes'] = 'Every nonempty workbook cell is preserved in CSV source_payload, with values verified against the workbook. Introductory/header and otherwise empty calendar rows attach to an adjacent record; no fake event is created for them. Original formula text is retained; no formulas are evaluated. Ambiguous measurements remain blank with original values in review_notes. Repeated records merge source provenance without dropping raw cells.'
    (output_path / "manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False), encoding="utf-8")
    counts = manifest['counts']
    enabled = manifest['enabled_counts']
    readme = f"""WAYMARK PROGRAMME CSV REVIEW PACKAGE

walks.csv: {counts['walks']} records; {enabled['walks']} enabled and {counts['walks'] - enabled['walks']} disabled.
holidays.csv: {counts['holidays']} records; {enabled['holidays']} enabled.
events.csv: {counts['events']} records; {enabled['events']} enabled (socials, meals, meetings and similar activities).

All {len(expected):,} nonempty source cells are preserved in the CSV source_payload fields, with values verified against the workbook. Header/introduction and otherwise empty calendar rows attach privately to adjacent records. Copied material to the right of Historic is preserved without creating fake events. The workbook is unchanged.

REVIEW
Read review_notes and manifest.json before uploading. Recovered historical dates, uncertain classifications, ambiguous measurements and inconsistent finish times are flagged. Original contact details remain in private source data; keep these files private. Dates recovered from prose/context should be checked against the source references. Ambiguous measurements such as 4 max are left unconfirmed; the exact wording remains in review notes and source data. Fractions become decimals.

A known date with no start time uses start_time_unknown=true and an internal midnight placeholder. The updated site displays time not recorded. Undated ideas have import_enabled=false and are preserved but skipped. An apostrophe protects any formula-like text in ordinary CSV cells when opened in spreadsheet software; original text remains in source_payload.

UPLOAD
Deploy the updated site and its database migration first. Open System > Imports and exports. Select Walks, Holidays or Events to match the file, upload that CSV, read columns, review the auto-mapped preview and import private drafts. Repeat for the other files. Existing matching records are skipped, never overwritten. Review drafts before publication. Imported source record in the editor shows the private original cells and review notes.

Configure the source grading labels (Easy, Leisurely, Moderate, Strenuous, Technical) in Waymark or edit the CSV grade values to match your installation. Leave leader_email blank for name-only leaders; do not invent account addresses. No accounts are created by importing these files.

VALIDATION
manifest.json contains source-cell reconciliation and classification review. When supplied, import-validation.json records an isolated test import using SQLite memory only. No records are imported into your actual site by this conversion tool.
"""
    (output_path / "README.txt").write_text(readme, encoding="utf-8")
    return manifest


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Convert a legacy walks workbook into review CSVs.")
    parser.add_argument("workbook")
    parser.add_argument("output")
    arguments = parser.parse_args()
    result = convert(arguments.workbook, arguments.output)
    print(json.dumps(result["counts"], indent=2))
