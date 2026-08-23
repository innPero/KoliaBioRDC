#!/usr/bin/env python3
import argparse
import csv
import json
import re
from pathlib import Path


PRODUCT_INSERT_RE = re.compile(
    r"INSERT INTO products\s*\((.*?)\)\s*VALUES\s*(.*?);",
    re.IGNORECASE | re.DOTALL,
)


def split_sql_tuples(values_blob: str):
    tuples = []
    buf = []
    depth = 0
    in_string = False
    escape = False
    for ch in values_blob:
        if in_string:
            buf.append(ch)
            if escape:
                escape = False
            elif ch == "\\":
                escape = True
            elif ch == "'":
                in_string = False
            continue

        if ch == "'":
            in_string = True
            buf.append(ch)
            continue
        if ch == "(":
            depth += 1
        if depth > 0:
            buf.append(ch)
        if ch == ")":
            depth -= 1
            if depth == 0:
                tuples.append("".join(buf))
                buf = []
    return tuples


def parse_tuple(raw: str):
    row = next(csv.reader([raw[1:-1]], quotechar="'", delimiter=",", skipinitialspace=True))
    return [item.strip() for item in row]


def parse_products(sql_text: str):
    m = PRODUCT_INSERT_RE.search(sql_text)
    if not m:
        return []
    cols = [c.strip().strip("`") for c in m.group(1).split(",")]
    tuples = split_sql_tuples(m.group(2))
    docs = []
    for tup in tuples:
        values = parse_tuple(tup)
        raw = dict(zip(cols, values))
        docs.append(
            {
                "$id": raw["id"],
                "nom": raw["nom"],
                "prix": int(raw["prix"]),
                "unite": raw["unite"],
                "categorie": raw["categorie"],
                "stock": int(raw["stock"]),
                "description": None if raw["description"].upper() == "NULL" else raw["description"],
                "image": None if raw["image"].upper() == "NULL" else raw["image"],
                "actif": True,
            }
        )
    return docs


def csv_to_json(path: Path):
    rows = []
    with path.open(newline="", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            rows.append(row)
    return rows


def main():
    parser = argparse.ArgumentParser(description="Convertit SQL/CSV legacy vers JSON Appwrite.")
    parser.add_argument("--sql", type=Path, help="Chemin vers luzolo_db.sql")
    parser.add_argument("--orders-csv", type=Path, help="CSV des commandes historiques", default=None)
    parser.add_argument("--order-items-csv", type=Path, help="CSV des lignes de commande historiques", default=None)
    parser.add_argument("--out-dir", type=Path, default=Path("migrations/out"))
    args = parser.parse_args()

    args.out_dir.mkdir(parents=True, exist_ok=True)

    if args.sql:
        docs = parse_products(args.sql.read_text(encoding="utf-8"))
        (args.out_dir / "products.json").write_text(json.dumps(docs, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"Produits exportés: {len(docs)} -> {(args.out_dir / 'products.json')}")

    if args.orders_csv:
        orders = csv_to_json(args.orders_csv)
        (args.out_dir / "orders.json").write_text(json.dumps(orders, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"Commandes exportées: {len(orders)}")

    if args.order_items_csv:
        items = csv_to_json(args.order_items_csv)
        (args.out_dir / "order_items.json").write_text(json.dumps(items, ensure_ascii=False, indent=2), encoding="utf-8")
        print(f"Lignes exportées: {len(items)}")


if __name__ == "__main__":
    main()
