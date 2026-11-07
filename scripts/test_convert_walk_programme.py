import importlib.util
import json
import tempfile
import unittest
from datetime import datetime, time
from pathlib import Path

from openpyxl import Workbook


SCRIPT = Path(__file__).with_name("convert-walk-programme.py")


def load_converter():
    spec = importlib.util.spec_from_file_location("convert_walk_programme", SCRIPT)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


class ConvertWalkProgrammeTests(unittest.TestCase):
    def test_real_programme_headings_map_measurements_and_separate_location_references(self):
        converter = load_converter()
        with tempfile.TemporaryDirectory() as directory:
            source, output = Path(directory) / 'source.xlsx', Path(directory) / 'out'
            workbook = Workbook(); sheet = workbook.active; sheet.title = 'Walks'
            sheet.append(['Date', 'Title', 'Description', 'Start Time', 'Difficulty', 'Dist\nMiles', 'Ascent feet', 'Google Satnav & What3Words', 'Est finish time (not guaranteed)'])
            sheet.append([datetime(2026, 1, 1), 'Chatsworth', 'Route via Hotel and Pub', time(10), 'Easy', '7½', 1000, 'Village car park\n///apple.river.hill', time(14, 30)])
            workbook.save(source)
            converter.convert(source, output)
            row = converter.read_csv(output / 'walks.csv')[0]
            self.assertEqual(row['distance'], '7.5')
            self.assertEqual(row['ascent'], '1000')
            self.assertEqual(row['what3words'], 'apple.river.hill')
            self.assertEqual(row['meeting_location_name'], 'Village car park')
            self.assertEqual(row['ends_at'], '2026-01-01 14:30:00')

    def test_item_title_and_explicit_route_fields_control_classification(self):
        converter = load_converter()
        self.assertEqual(converter.classify('Walks', 'Not Christmas Meal', 'Hotel accommodation available', {})[0], 'events')
        self.assertEqual(converter.classify('Walks', 'Dolgellau', 'Bookings open at the January social. Sole use of hostel for the weekend.', {})[0], 'holidays')
        self.assertEqual(converter.classify('Walks', 'St Briavels Weekend', 'Bookings open at the social', {})[0], 'holidays')
        self.assertEqual(converter.classify('Walks', 'Youlgreave Christmas Extravaganza!', 'Meal and stay at YHA', {})[0], 'holidays')
        self.assertEqual(converter.classify('Historic', 'Cromford', 'Christmas social afterwards', {'difficulty': 'Easy'})[0], 'walks')
        self.assertEqual(converter.classify('Walks', 'Strathcarron - SOLD OUT', 'Staying at a lodge for the week', {})[0], 'holidays')

    def test_uncertain_measurements_are_preserved_for_review_without_inventing_a_number(self):
        converter = load_converter()
        with tempfile.TemporaryDirectory() as directory:
            source, output = Path(directory) / 'source.xlsx', Path(directory) / 'out'
            workbook = Workbook(); sheet = workbook.active; sheet.title = 'Walks'
            sheet.append(['Date', 'Title', 'Difficulty', 'Dist Miles', 'Ascent feet'])
            sheet.append([datetime(2023, 1, 1), 'Route', 'Easy', '4 max', 'Circular'])
            workbook.save(source)
            converter.convert(source, output)
            row = converter.read_csv(output / 'walks.csv')[0]
            self.assertEqual((row['distance'], row['ascent']), ('', ''))
            self.assertIn('4 max', row['review_notes'])
            self.assertIn('Circular', row['review_notes'])

    def test_explicit_clock_notation_is_read_but_alternative_or_approximate_times_are_not_guessed(self):
        converter = load_converter()
        self.assertEqual(converter.clock_time('10.30 (BST)'), '10:30:00')
        self.assertEqual(converter.clock_time('08:25:00 prompt to catch a bus.'), '08:25:00')
        self.assertEqual(converter.clock_time('A: 08:00 B: 08:45'), '')
        self.assertEqual(converter.clock_time('check trains arriving approx 10.30'), '')

    def test_walk_markers_override_route_words_and_titles_use_first_line(self):
        converter = load_converter()
        with tempfile.TemporaryDirectory() as temporary_directory:
            source, output = Path(temporary_directory) / "source.xlsx", Path(temporary_directory) / "out"
            workbook = Workbook(); sheet = workbook.active; sheet.title = "Walks"
            sheet.append(["Date", "Title", "Description", "Linear or Circular", "Distance (miles)", "Grade"])
            sheet.append([datetime(2025, 1, 1), "Shuttlingsloe\nvia a cottage", "Route via pub and hotel\nFull route retained", "Circular", 10.5, "Moderate"])
            sheet.append([datetime(2025, 1, 2), "Nottingham Social - Snowdonia booking opens", "Pub social; holiday bookings open", "", "", ""])
            workbook.save(source)
            converter.convert(source, output)
            walks = converter.read_csv(output / "walks.csv")
            self.assertEqual(len(walks), 1)
            self.assertEqual(walks[0]["title"], "Shuttlingsloe")
            self.assertIn("Full route retained", walks[0]["description"])
            self.assertEqual(walks[0]["distance"], "10.5")
            self.assertEqual(walks[0]["grade"], "Moderate")
            event = converter.read_csv(output / "events.csv")[0]
            self.assertEqual(event["title"], "Nottingham Social - Snowdonia booking opens")

    def test_sheet1_shifted_columns_and_blank_calendar_slots(self):
        converter = load_converter()
        with tempfile.TemporaryDirectory() as temporary_directory:
            source, output = Path(temporary_directory) / "source.xlsx", Path(temporary_directory) / "out"
            workbook = Workbook(); sheet = workbook.active; sheet.title = "Sheet1"
            row = [None] * 42
            row[0], row[2], row[3], row[4], row[7], row[8], row[9], row[10], row[11], row[12], row[13] = "Sun", datetime(2025, 1, 5), "Route", "Full description", "Leader", "Linear", time(10), "Start", "AB1 2CD", "SK123456", "Satnav ///three.words"
            row[24], row[25], row[26], row[27], row[28], row[29] = "Easy", 12, 7.5, 200, 650, "Dogs welcome"
            sheet.append(row)
            blank = [None] * 42; blank[0], blank[2] = "Sun", datetime(2025, 1, 12); sheet.append(blank)
            workbook.save(source)
            converter.convert(source, output)
            walks = converter.read_csv(output / "walks.csv")
            self.assertEqual(len(walks), 1)
            walk = walks[0]
            self.assertEqual((walk["grade"], walk["distance"], walk["distance_unit"], walk["ascent"], walk["ascent_unit"]), ("Easy", "7.5", "miles", "650", "feet"))
            manifest = json.loads((output / "manifest.json").read_text(encoding="utf-8"))
            self.assertEqual(len(manifest["unattached_auxiliary_rows"]), 1)

    def test_converts_typed_rows_and_retains_auxiliary_cells(self):
        converter = load_converter()
        with tempfile.TemporaryDirectory() as temporary_directory:
            workbook_path = Path(temporary_directory) / "programme.xlsx"
            output_path = Path(temporary_directory) / "output"
            workbook = Workbook()
            walks = workbook.active
            walks.title = "Walks"
            walks.append(["Walk programme introduction"])
            walks.append(["Date", "Title", "Leader", "Distance", "Notes"])
            walks.append([datetime(2024, 4, 2), "Clumber circular", "A. Leader", "10½ miles", "Meet at main gate"])
            socials = workbook.create_sheet("Socials")
            socials.append(["Date", "Activity", "Venue", "Notes"])
            socials.append([datetime(2024, 4, 3), "Pub meal – bookings open", "The Swan", "Book with organiser"])
            ideas = workbook.create_sheet("Ideas")
            ideas.append(["Potential route", "Comment"])
            ideas.append(["Whitwell circular", "Date to be agreed"])
            workbook.save(workbook_path)

            result = converter.convert(workbook_path, output_path)

            self.assertEqual(result["counts"], {"walks": 2, "holidays": 0, "events": 1})
            walk_rows = converter.read_csv(output_path / "walks.csv")
            clumber = next(row for row in walk_rows if row["title"] == "Clumber circular")
            self.assertEqual(clumber["distance"], "10.5")
            self.assertEqual(clumber["distance_unit"], "miles")
            self.assertEqual(clumber["start_time_unknown"], "true")
            self.assertEqual(clumber["import_enabled"], "true")
            self.assertIn("Walk programme introduction", json.dumps(json.loads(clumber["source_payload"])))
            idea = next(row for row in walk_rows if row["title"] == "Whitwell circular")
            self.assertEqual(idea["import_enabled"], "false")
            self.assertIn("no usable date", idea["review_notes"])
            event = converter.read_csv(output_path / "events.csv")[0]
            self.assertEqual(event["title"], "Pub meal – bookings open")
            self.assertEqual(event["venue_name"], "The Swan")

    def test_merges_exact_title_and_date_and_preserves_formula_text(self):
        converter = load_converter()
        with tempfile.TemporaryDirectory() as temporary_directory:
            workbook_path = Path(temporary_directory) / "programme.xlsx"
            output_path = Path(temporary_directory) / "output"
            workbook = Workbook()
            first = workbook.active
            first.title = "Walks"
            first.append(["Date", "Title", "Description"])
            first.append([datetime(2023, 1, 1), "New Year walk", "Short note"])
            historic = workbook.create_sheet("Historic")
            historic.append(["Date", "Title", "Extra"])
            historic.append([datetime(2023, 1, 1), "New Year walk", "=1+1"])
            workbook.save(workbook_path)

            result = converter.convert(workbook_path, output_path)

            self.assertEqual(result["counts"]["walks"], 1)
            row = converter.read_csv(output_path / "walks.csv")[0]
            payload = json.loads(row["source_payload"])
            historic_row = next(item for item in payload if item["sheet"] == "Historic" and item["row"] == 2)
            self.assertEqual(historic_row["cells"]["C2"]["formula"], "=1+1")


if __name__ == "__main__":
    unittest.main()
