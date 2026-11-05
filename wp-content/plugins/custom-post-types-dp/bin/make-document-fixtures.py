#!/usr/bin/env python3
"""Generate the realistic portfolio document fixtures used by the documents seeder.

Run inside DDEV:
    ddev exec python3 wp-content/plugins/custom-post-types-dp/bin/make-document-fixtures.py [--large]
"""

from argparse import ArgumentParser
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile
import re


ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "data/seeders/files/documents"
SOURCE = ROOT / "tests/fixtures"


def convert(source: Path, extension: str, output: Path) -> Path:
    with tempfile.TemporaryDirectory(prefix="cptdp-fixtures-") as work:
        target = Path(work) / "out"
        target.mkdir()
        profile = Path(work) / "profile"
        command = [
            "soffice", "--headless", "--norestore",
            f"-env:UserInstallation=file://{profile}",
            "--convert-to", extension, "--outdir", str(target), str(source),
        ]
        try:
            result = subprocess.run(command, check=True, timeout=180, capture_output=True, text=True)
        except subprocess.CalledProcessError as error:
            raise RuntimeError(f"LibreOffice failed: {error.stdout} {error.stderr}") from error
        converted = target / f"{source.stem}.{extension.split(':')[0]}"
        if not converted.is_file():
            raise RuntimeError(f"LibreOffice did not create {converted}: {result.stdout} {result.stderr}")
        shutil.copyfile(converted, output)
    return output


def repeat_pdf(source: Path, output: Path, pages: int):
    if pages == 1:
        return
    with tempfile.TemporaryDirectory(prefix="cptdp-pdf-") as work:
        target = Path(work) / output.name
        command = ["gs", "-q", "-dBATCH", "-dNOPAUSE", "-sDEVICE=pdfwrite", f"-sOutputFile={target}"] + [str(source)] * pages
        subprocess.run(command, check=True, timeout=180, capture_output=True, text=True)
        shutil.copyfile(target, output)


def fodt(pages, landscape_pages=(), profile="operations", page_number=1, total_pages=None, dense=False):
    layouts = """
    <style:page-layout style:name="portrait"><style:page-layout-properties fo:page-width="21cm" fo:page-height="29.7cm" style:print-orientation="portrait"/></style:page-layout>
    <style:page-layout style:name="landscape"><style:page-layout-properties fo:page-width="29.7cm" fo:page-height="21cm" style:print-orientation="landscape"/></style:page-layout>
    """
    masters = '<style:master-page style:name="Standard" style:page-layout-name="landscape" />' if landscape_pages else '<style:master-page style:name="Standard" style:page-layout-name="portrait" />'
    profiles = {
        "summary": (
            "Q3 2026 Portfolio Summary",
            "A practical snapshot of delivery across social media marketing, digital marketing and web creation.",
            "The quarter closed with more than 40 happy clients supported and a typical reply time of 24 hours.",
        ),
        "operations": (
            "Client Operations Playbook",
            "A repeatable workflow for turning a brief into approved content, campaign assets and a maintained website.",
            "The process covers discovery, content creation, scheduled posting, analytics and handover.",
        ),
        "analytics": (
            "Social Media Analytics Report",
            "A concise reporting format for reviewing reach, engagement, content output and next actions.",
            "Use the same reporting rhythm each month so decisions are based on comparable numbers.",
        ),
        "strategy": (
            "Digital Marketing Strategy",
            "A focused plan connecting audience growth, campaign creative and measurable customer outcomes.",
            "Start with a clear content calendar, then use ad analytics and CRM feedback to refine the next cycle.",
        ),
    }
    heading, intro, detail = profiles[profile]
    total_pages = total_pages or pages
    body = []
    for page in range(1, pages + 1):
        style = "PageBreak" if page > 1 else "Heading"
        body.append(
            f'<text:p text:style-name="{style}">'
            f"{heading} — page {page_number if pages == 1 else page} of {total_pages}</text:p>"
        )
        body.append(f"<text:p>{intro}</text:p>")
        body.append(f"<text:p>{detail}</text:p>")
        body.append(f"<text:p>Priority for this section: document the decision, assign the owner and record the next action before the review closes.</text:p>")
        if dense:
            for paragraph in range(1, 18):
                body.append(f"<text:p>Implementation note {paragraph}: keep the brief current, publish the approved asset, review the result and carry the learning into the next content calendar.</text:p>")
        if page == 2 and profile == "operations":
            body.append(
                "<table:table xmlns:table=\"urn:oasis:names:tc:opendocument:xmlns:table:1.0\">"
                "<table:table-row><table:table-cell><text:p>Workstream</text:p></table:table-cell><table:table-cell><text:p>Owner</text:p></table:table-cell><table:table-cell><text:p>Cadence</text:p></table:table-cell></table:table-row>"
                "<table:table-row><table:table-cell><text:p>Content calendar</text:p></table:table-cell><table:table-cell><text:p>Marketing lead</text:p></table:table-cell><table:table-cell><text:p>Weekly</text:p></table:table-cell></table:table-row>"
                "<table:table-row><table:table-cell><text:p>Scheduled posting</text:p></table:table-cell><table:table-cell><text:p>Content producer</text:p></table:table-cell><table:table-cell><text:p>Daily</text:p></table:table-cell></table:table-row>"
                "<table:table-row><table:table-cell><text:p>Ad analytics</text:p></table:table-cell><table:table-cell><text:p>Account owner</text:p></table:table-cell><table:table-cell><text:p>Monthly</text:p></table:table-cell></table:table-row>"
                "</table:table>"
            )
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" office:version="1.2">
<office:font-face-decls/><office:automatic-styles>
<style:style style:name="Standard" style:family="paragraph"/>
<style:style style:name="Heading" style:family="paragraph" style:parent-style-name="Standard"><style:text-properties fo:font-size="18pt" fo:font-weight="bold"/></style:style>
<style:style style:name="PageBreak" style:family="paragraph" style:parent-style-name="Standard"><style:paragraph-properties fo:break-before="page"/></style:style>
{layouts}</office:automatic-styles><office:master-styles>{masters}</office:master-styles>
<office:body><office:text>{''.join(body)}</office:text></office:body></office:document>"""


def fodp(slides: int, width: str, height: str):
    template = (SOURCE / "sample.fodp").read_text(encoding="utf-8")
    template = template.replace('fo:page-width="28cm" fo:page-height="15.75cm"', f'fo:page-width="{width}" fo:page-height="{height}"')
    matches = re.findall(r"  <draw:page .*?</draw:page>", template, re.DOTALL)
    pages = []
    content = [
        ("Growth plan", "Social media marketing, digital marketing and web creation"),
        ("Delivery model", "Content calendar, scheduled posting and analytics"),
        ("Proof points", "+5 yrs experience, +40 happy clients, 24 hr typical reply"),
        ("Next quarter", "Strengthen reporting, improve handover and expand WordPress support"),
    ]
    for number in range(1, slides + 1):
        page = matches[(number - 1) % len(matches)]
        page = re.sub(r'draw:name="page\d+"', f'draw:name="page{number}"', page)
        title, body = content[(number - 1) % len(content)]
        page = re.sub(r"Slide (one|two|three)", title, page, count=1)
        page = re.sub(r"Fixture slide \d+ of \d+", body, page)
        pages.append(page)
    return template[:template.index("  <draw:page")] + "\n".join(pages) + "\n </office:presentation></office:body></office:document>\n"


def fods():
    rows = "".join(f"<table:table-row><table:table-cell office:value-type=\"string\"><text:p>Row {row}</text:p></table:table-cell>" + "".join("<table:table-cell office:value-type=\"float\" office:value=\"1\"><text:p>1</text:p></table:table-cell>" for _ in range(40)) + "</table:table-row>" for row in range(20))
    summary = '<table:table table:name="Summary"><table:table-row><table:table-cell office:value-type="string"><text:p>Summary</text:p></table:table-cell></table:table-row></table:table>'
    wide = f'<table:table table:name="Wide">{rows}</table:table>'
    hidden = '<table:table table:name="Hidden notes" table:style-name="Hidden"><table:table-row><table:table-cell office:value-type="string"><text:p>Hidden notes</text:p></table:table-cell></table:table-row></table:table>'
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" office:version="1.2">
<office:automatic-styles><style:style style:name="Hidden" style:family="table"><style:table-properties table:display="false"/></style:style></office:automatic-styles>
<office:body><office:spreadsheet>{summary}{wide}{hidden}</office:spreadsheet></office:body></office:document>"""


def hide_xlsx_sheet(source: Path, output: Path):
    with zipfile.ZipFile(source) as archive:
        files = {name: archive.read(name) for name in archive.namelist()}
    workbook = files["xl/workbook.xml"].replace(
        b'<sheet name="Headcount"', b'<sheet state="hidden" name="Headcount"'
    )
    files["xl/workbook.xml"] = workbook
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as archive:
        for name, content in files.items():
            archive.writestr(name, content)


def write_large():
    generated = OUT / "generated"
    generated.mkdir(exist_ok=True)
    workbook = generated / "workbook-10mb.xlsx"
    word = generated / "word-10mb.docx"
    with tempfile.TemporaryDirectory(prefix="cptdp-large-") as work:
        source = Path(work) / "large.fods"
        source.write_text(fods().replace("</office:spreadsheet>", "".join(
            f'<table:table table:name="Data {n}">{("<table:table-row><table:table-cell office:value-type=\"string\"><text:p>{n}-synthetic-data</text:p></table:table-cell></table:table-row>" * 2000)}</table:table>'
            for n in range(20)
        ) + "</office:spreadsheet>"), encoding="utf-8")
        convert(source, "xlsx:Calc MS Excel 2007 XML", workbook)
        while workbook.stat().st_size < 10 * 1024 * 1024:
            with workbook.open("ab") as handle:
                handle.write(b"synthetic-padding-" * 1024)
        source.write_text(fodt(1200), encoding="utf-8")
        convert(source, "docx:Office Open XML Text", word)
    print(f"{workbook.name}: {workbook.stat().st_size} bytes")
    print(f"{word.name}: {word.stat().st_size} bytes")


def main():
    parser = ArgumentParser()
    parser.add_argument("--large", action="store_true")
    args = parser.parse_args()
    OUT.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(prefix="cptdp-sources-") as work:
        source = Path(work)
        def make_text(name, pages, landscape=()):
            path = source / f"{name}.fodt"
            path.write_text(fodt(pages, landscape), encoding="utf-8")
            convert(path, "pdf", OUT / f"{name}.pdf")
            return OUT / f"{name}.pdf"
        def make_pdf(name, pages, landscape=(), profile="operations"):
            output = OUT / f"{name}.pdf"
            if pages == 1:
                path = source / f"{name}.fodt"
                path.write_text(fodt(1, landscape, profile, 1, 1), encoding="utf-8")
                convert(path, "pdf", output)
                return
            page_pdfs = []
            for page_number in range(1, pages + 1):
                path = source / f"{name}-{page_number}.fodt"
                path.write_text(fodt(1, landscape, profile, page_number, pages), encoding="utf-8")
                page_pdfs.append(str(convert(path, "pdf", source / f"{name}-{page_number}.pdf")))
            command = ["gs", "-q", "-dBATCH", "-dNOPAUSE", "-sDEVICE=pdfwrite", f"-sOutputFile={output}"] + page_pdfs
            subprocess.run(command, check=True, timeout=180, capture_output=True, text=True)

        make_pdf("pdf-portrait-1p", 1, profile="analytics")
        make_pdf("pdf-portrait-3pp", 3, profile="summary")
        make_pdf("pdf-portrait-4pp", 4, profile="operations")
        make_pdf("pdf-landscape-1p", 1, (1,), "strategy")
        make_pdf("pdf-landscape-3pp", 3, (1, 2, 3), "strategy")
        make_pdf("pdf-portrait-24pp", 24, profile="operations")
        (OUT / "pdf-corrupt.pdf").write_bytes((OUT / "pdf-portrait-4pp.pdf").read_bytes()[:1500])
        slide = source / "pptx-1-slide.fodp"
        slide.write_text(fodp(1, "28cm", "15.75cm"), encoding="utf-8")
        convert(slide, "pptx:Impress MS PowerPoint 2007 XML", OUT / "pptx-1-slide.pptx")
        slide.write_text(fodp(4, "28cm", "21cm"), encoding="utf-8")
        convert(slide, "pptx:Impress MS PowerPoint 2007 XML", OUT / "pptx-4x3.pptx")
        (OUT / "pptx-corrupt.pptx").write_bytes((OUT / "pptx-4x3.pptx").read_bytes()[:2048])
        for name in ("sample.pptx", "sample.ppt", "sample.odp", "sample.xlsx", "sample.xls", "sample.ods", "sample.docx", "sample.doc", "sample.odt", "sample-operations-playbook.docx"):
            shutil.copyfile(SOURCE / name, OUT / name)
        hide_xlsx_sheet(SOURCE / "sample.xlsx", OUT / "xlsx-hidden-sheet.xlsx")
        (OUT / "xlsx-corrupt.xlsx").write_bytes((OUT / "xlsx-hidden-sheet.xlsx").read_bytes()[:2048])
        doc = source / "docx-1p.fodt"
        doc.write_text(fodt(1, profile="analytics"), encoding="utf-8")
        convert(doc, "docx:Office Open XML Text", OUT / "docx-1p.docx")
        doc.write_text(fodt(12, (7, 8), "operations", dense=True), encoding="utf-8")
        convert(doc, "docx:Office Open XML Text", OUT / "docx-12pp.docx")
        (OUT / "docx-corrupt.docx").write_bytes((OUT / "docx-12pp.docx").read_bytes()[:2048])
    if args.large:
        write_large()
    expected = [path for path in OUT.iterdir() if path.is_file()]
    total = sum(path.stat().st_size for path in expected)
    for path in sorted(expected):
        if path.stat().st_size == 0:
            raise RuntimeError(f"Empty fixture: {path}")
        print(f"{path.name}: {path.stat().st_size} bytes")
    print(f"Total committed fixture size: {total} bytes")
    if total > 2 * 1024 * 1024:
        print("WARNING: committed fixtures exceed 2 MiB")


if __name__ == "__main__":
    main()
