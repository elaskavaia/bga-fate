# Graphics Assets

Original game assets (PDFs) are in `~/Develop/bga/bga-assets/fate/Cards/`:
- Per-hero PDFs: `EN_<Hero>_Fronts.pdf`, `EN_<Hero>_Backs.pdf` (40 pages each)
- Shared equipment: `EN_Equipment_Fronts.pdf` (promo pack, 7 pages including cover)
- Monster cards: `EN_Threats_Fronts.pdf`, `EN_Threats_Backs.pdf`
- Text extractions: `.txt` files alongside each PDF

Converted JPG sprites are in `~/Develop/bga/bga-assets/fate/Cards/JPG/`.
The `monim.sh` script in that directory converts PDFs to 6-column sprite sheets using ImageMagick montage.

## Hero card sprite conversion

Each hero has a `reorder_<hero>.sh` script that:
1. Extracts individual cards from the Fronts, Backs, and Equipment sprites
2. Reorders them by `num` field from `card_material.csv` (1-34)
3. Adds 2 generic backs (equipment + event) as positions 35-36
4. Assembles into a 6x6 sprite: `<hero>_hero_cards.jpg` (288x393 per card)

The mapping in each script maps PDF sprite positions to CSV `num` values:
- Fronts sprite: hero Level I, starting ability/equip, then remaining abilities, equipment, events (with duplicates for count>1)
- Backs sprite: hero Level II + ability Level IIs (first 8 positions), then generic equip/event backs
- Equipment sprite: promo pack cards (position varies per hero)

Output sprites are copied to `img/` in this repo for use by CSS.
