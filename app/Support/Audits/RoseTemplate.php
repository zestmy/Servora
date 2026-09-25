<?php

namespace App\Support\Audits;

use App\Models\AuditTemplate;
use App\Models\AuditTemplateItem;
use App\Models\AuditTemplateSection;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The ROSE starter form — Restaurant Operations System Evaluation.
 *
 * Transcribed from a QA department's printed form so a company has something
 * real to start from rather than a blank builder. Brand-specific product names
 * on the original have been replaced with generic ones ("Hot coffee base",
 * "Noodle soup stock"): every company renames them to its own menu on the
 * Audit Forms screen, which is the point of it being a template.
 *
 * SHAPE. Five sections. The first is a PENALTY section: fourteen critical
 * food-safety and halal items at 10 points each whose deductions come off the
 * audit total after the four area scores are pooled. The areas are trees of
 * numbered items with lettered sub-items at 2 points each, plus PRODUCT slots
 * ("Toast 1", "Noodle 2") the auditor fills with whatever they tasted that
 * day, scored on four fixed criteria.
 *
 * Every label carries the Bahasa Malaysia beside it, as the printed form does.
 *
 * Item notation, kept terse because there are three hundred of them:
 *   [number, label, label_alt, points]                a leaf
 *   [number, label, label_alt, 0, [children…]]        a heading with sub-items
 *   [number, label, label_alt, 0, [children…], 'product']  a product slot
 * A hint (an equipment setting) is a sixth element on a leaf.
 */
class RoseTemplate
{
    public const CODE = 'ROSE';

    public static function install(Company $company, ?User $by = null): AuditTemplate
    {
        return DB::transaction(function () use ($company, $by) {
            $template = AuditTemplate::create([
                'company_id'               => $company->id,
                'name'                     => 'Restaurant Operations System Evaluation',
                'code'                     => self::CODE,
                'description'              => 'Outlet operations audit: critical food safety and halal items, then bar, kitchen, service and documentation. Starter form — rename the product slots and stock items to your own menu.',
                'alt_language'             => 'Bahasa Malaysia',
                'is_active'                => true,
                'version'                  => 1,
                'header_fields'            => self::headerFields(),
                'requires_acknowledgement' => true,
                'created_by'               => $by?->id,
            ]);

            foreach (self::sections() as $order => $sectionData) {
                $section = AuditTemplateSection::create([
                    'audit_template_id' => $template->id,
                    'name'              => $sectionData['name'],
                    'name_alt'          => $sectionData['name_alt'],
                    'scoring_mode'      => $sectionData['mode'],
                    'sort_order'        => $order,
                ]);

                $sort = 0;
                self::createItems($section, null, $sectionData['items'], $sort);
            }

            return $template;
        });
    }

    private static function createItems(AuditTemplateSection $section, ?int $parentId, array $items, int &$sort): void
    {
        foreach ($items as $item) {
            [$number, $label, $alt, $points] = $item;
            $children = $item[4] ?? [];
            $type     = ($item[5] ?? null) === 'product' ? AuditTemplateItem::TYPE_PRODUCT : AuditTemplateItem::TYPE_CHECK;
            $hint     = is_string($item[5] ?? null) && $type === AuditTemplateItem::TYPE_CHECK ? $item[5] : null;

            $row = AuditTemplateItem::create([
                'audit_template_section_id' => $section->id,
                'parent_id'  => $parentId,
                'number'     => $number,
                'label'      => $label,
                'label_alt'  => $alt,
                'hint'       => $hint,
                'type'       => $type,
                'points'     => $children ? 0 : $points,
                'sort_order' => $sort++,
            ]);

            if ($children) {
                self::createItems($section, $row->id, $children, $sort);
            }
        }
    }

    public static function headerFields(): array
    {
        return [
            ['key' => 'shift_officer',  'label' => 'Shift Officer on duty',            'type' => 'employee', 'required' => false],
            ['key' => 'team_on_duty',   'label' => 'Team on duty (headcount)',         'type' => 'number',   'required' => false],
            ['key' => 'muslim_staff',   'label' => 'Malaysian Muslim staff on duty',   'type' => 'number',   'required' => false],
            ['key' => 'area_manager',   'label' => 'Outlet / Area Manager',            'type' => 'text',     'required' => false],
        ];
    }

    // ── The four product-slot criteria, repeated under every slot ────────

    private static function productCriteria(): array
    {
        return [
            ['a', 'Correct procedure',              'Prosedur yang betul',                 4],
            ['b', 'Correct ingredients and amount', 'Bahan dan jumlah bahan yang betul',   2],
            ['c', 'Correct tools',                  'Peralatan yang betul',                2],
            ['d', 'Correct presentation',           'Persembahan yang betul',              2],
        ];
    }

    private static function productSlot(string $number, string $label, string $alt): array
    {
        return [$number, $label, $alt, 0, self::productCriteria(), 'product'];
    }

    private static function others(string $number = ''): array
    {
        return [$number, 'Others (if any)', 'Lain-lain (jika ada)', 2];
    }

    public static function sections(): array
    {
        return [
            [
                'name'     => 'Main Food Safety / Halal Non-Compliances',
                'name_alt' => 'Ketidakpatuhan Keselamatan Makanan / Halal Utama',
                'mode'     => AuditTemplateSection::MODE_PENALTY,
                'items'    => [
                    ['1',  'Products must be from approved sources (category A, B, C). Outside / non-halal food is not kept in the outlet', 'Produk hendaklah dari sumber yang diluluskan (Kategori A, B, C). Produk luar atau produk tidak halal tidak disimpan di outlet', 10],
                    ['2',  'No form of deity or religious statue is present in the premise', 'Tidak terdapat sebarang alat penyembahan atau simbol keagamaan di premis', 10],
                    ['3',  'One Malaysian-citizen Muslim staff is on duty at the outlet', 'Seorang staf Muslim warganegara Malaysia bertugas di premis', 10],
                    ['4',  'Staff have attended Food Handlers Training and certificates are available at the outlet', 'Staf telah menghadiri Latihan Pengendali Makanan dan sijil disimpan di premis', 10],
                    ['5',  'Staff hold a valid typhoid injection card / letter and the record is kept at the outlet. No staff observed working with symptoms of illness or infection', 'Staf mempunyai kad / surat suntikan typhoid yang sah dan rekod disimpan di outlet. Staf yang bertugas tidak mempunyai tanda-tanda sakit atau jangkitan', 10],
                    ['6',  'Shift Officer (SO) on duty is certified by the Training Department', 'Shift Officer (SO) yang bertugas telah disahkan oleh Jabatan Latihan', 10],
                    ['7',  'Staff / SO dress code, grooming and personal hygiene meet brand requirements. Proper disposable glove procedure is followed', 'Pemakaian, penampilan dan kebersihan diri staf / SO memenuhi kehendak jenama. Prosedur pemakaian sarung tangan yang betul dipatuhi', 10],
                    ['8',  'SOPs are accessible at the outlet', 'SOP boleh diakses di outlet', 10],
                    ['9',  'A calibrated thermometer is available for use', 'Jangka suhu yang dikalibrasi sedia ada digunakan', 10],
                    ['10', 'Freezer / chiller temperature is within the standard', 'Suhu peti beku / peti sejuk dalam lingkungan standard', 10],
                    ['11', 'Products are within expiry date, not spoiled or mouldy', 'Produk masih belum tamat tempoh / tidak rosak atau berkulat', 10],
                    ['12', 'No cross-contamination issue observed', 'Tidak terdapat isu pencemaran silang', 10],
                    ['13', 'All chemicals available, properly stored, correctly diluted and from an approved supplier', 'Bahan kimia tersedia, dalam simpanan yang baik, pencairan yang betul dan dari pembekal diluluskan', 10],
                    ['14', 'Pest control treatment is carried out and the report is available. No sign of pest infestation', 'Rawatan makhluk perosak telah diadakan dan laporan sedia ada. Tiada tanda-tanda kehadiran makhluk perosak', 10],
                ],
            ],

            [
                'name'     => 'Bar Area',
                'name_alt' => 'Kawasan Bar',
                'mode'     => AuditTemplateSection::MODE_AREA,
                'items'    => [
                    ['1', 'Equipment in good and clean condition', 'Peralatan dalam keadaan yang baik dan bersih', 0, [
                        ['a', 'Coffee maker',                                   'Pembancuh kopi',                                        2],
                        ['b', 'Hot water boiler',                               'Pemanas air',                                           2, [], 'Setting: 100°C'],
                        ['c', 'Coffee cup warmer',                              'Pemanas cawan kopi',                                    2, [], 'Setting: 70°C'],
                        ['d', 'Drink mixer / steamer',                          'Pembancuh minuman',                                     2],
                        ['e', 'Ice blended machine',                            'Mesin ais kisar',                                       2],
                        ['f', 'Electric bread toaster',                         'Pembakar roti elektrik',                                2],
                        ['g', 'Air frying oven',                                'Ketuhar penggoreng udara',                              2],
                        ['h', 'Can opener',                                     'Pembuka tin',                                           2],
                        ['i', 'Chiller',                                        'Peti sejuk',                                            2, [], 'Setting: 0°C – 4°C'],
                        ['j', 'Freezer',                                        'Peti beku',                                             2, [], 'Setting: below −18°C'],
                        ['k', 'Postmix machine / ice bin / ice machine',        'Mesin postmix / bekas ais / mesin ais',                 2],
                        ['l', 'Water filter',                                   'Penapis air',                                           2],
                        ['m', 'Bar sink',                                       'Sinki bar',                                             2],
                        ['n', 'Grease trap',                                    'Perangkap minyak',                                      2],
                        ['o', 'Preparation table / shelves rack / cabinet',     'Meja penyediaan / rak / kabinet',                       2],
                        ['p', 'Cup sealer',                                     'Pengedap cawan',                                        2],
                        ['q', 'Insect killer',                                  'Pembasmi serangga',                                     2],
                        ['r', 'Docket holder / printer',                        'Pemegang doket / mesin cetakan',                        2],
                        ['s', 'Digital timer',                                  'Penyukat masa digital',                                 2],
                        ['t', 'Ice shaver',                                     'Pengisar ais',                                          2],
                        ['u', 'Foot-pedal trash bin lined with a garbage bag and properly covered', 'Tong sampah pijak kaki mempunyai beg sampah dan ditutup', 2],
                        ['v', 'Hand towel / hand soap / hand sanitiser dispenser', 'Bekas tuala tangan / bekas sabun tangan / bekas sanitizer tangan', 2],
                        self::others('w'),
                    ]],
                    ['2', 'Utensils are available and in good, clean condition', 'Perkakas lengkap dan dalam keadaan baik dan bersih', 0, [
                        ['a', 'Smallware',                                      'Alatan kecil',                                          2],
                        ['b', 'Stainless mug / mixer container / strainer',     'Mug S/S / bekas pembancuh / penapis',                   2],
                        ['c', 'Cutting board / knives / scissors',              'Papan pemotong / pisau / gunting',                      2],
                        ['d', 'Measurement jug / weighing scale',               'Jag penyukat / penimbang',                              2],
                        ['e', 'Half-boiled egg holder',                         'Bekas telur separuh masak',                             2],
                        ['f', 'Dishware and drinkware',                         'Peralatan pinggan mangkuk dan cawan',                   2],
                        ['g', 'Scoop',                                          'Pencedok',                                              2],
                        ['h', 'Container and cover',                            'Bekas dan penutup',                                     2],
                        ['i', 'Cutlery holder',                                 'Pemegang sudu / garpu',                                 2],
                        ['j', 'Bamboo basket',                                  'Bakul rotan',                                           2],
                        ['k', 'Brush',                                          'Berus',                                                 2],
                        self::others('l'),
                    ]],
                    ['3',  'Coffee grounds are discarded immediately after every batch of preparation', 'Serbuk kopi hendaklah dibuang serta-merta selepas penyediaan', 4],
                    ['4',  'Food / containers are clearly labelled with the correct shelf life. No potential cross-contamination observed', 'Produk / bekas dilabelkan dengan jangka hayat yang betul. Tiada potensi pencemaran silang', 6],
                    ['5',  'Canned products are transferred to an appropriate container after opening', 'Produk dalam tin disimpan di dalam bekas yang lain selepas dibuka', 4],
                    ['6',  'Products are covered when not in use, held and rotated properly (FIFO), and placed according to the stock arrangement', 'Produk ditutup apabila tidak digunakan, disimpan dan dipusingkan dengan betul (FIFO), dan diletakkan mengikut susunan stok', 4],
                    ['7',  'Different products are kept individually in separate covered containers', 'Produk yang berlainan disimpan dalam bekas bertutup secara berasingan', 4],
                    ['8',  'Bases are prepared in compliance with the brand SOP', 'Bes disediakan mengikut SOP jenama', 0, [
                        ['a', 'Hot coffee base',                                'Bes kopi panas',                                        2],
                        ['b', 'Cold coffee base',                               'Bes kopi sejuk',                                        2],
                        ['c', 'Chocolate base',                                 'Bes coklat',                                            2],
                        ['d', 'Black coffee base',                              'Bes kopi O',                                            2],
                        ['e', 'Instant tea base',                               'Bes teh segera',                                        2],
                        ['f', 'Cold brew shot',                                 'Shot cold brew',                                        2],
                        ['g', 'Lime base',                                      'Bes limau',                                             2],
                        ['h', 'Asam boi base',                                  'Bes asam boi',                                          2],
                        ['i', 'Milk tea base',                                  'Bes teh susu',                                          2],
                        ['j', 'Fresh lemon tea base',                           'Bes teh lemon segar',                                   2],
                        ['k', 'Teh tarik base',                                 'Bes teh tarik',                                         2],
                        ['l', 'Sugar syrup',                                    'Sirap gula',                                            2],
                        self::others('m'),
                    ]],
                    ['9', 'Handling and storage according to guidelines', 'Pengendalian dan penyimpanan mengikut panduan', 0, [
                        ['a', 'Beverage creamer / evaporated milk',             'Krimer / susu sejat',                                   2],
                        ['b', 'Coconut milk',                                   'Santan',                                                2],
                        ['c', 'UHT milk / oat milk',                            'Susu UHT / susu oat',                                   2],
                        ['d', 'Bread / bun',                                    'Roti / bun',                                            2],
                        ['e', 'Butter / spread',                                'Mentega / bahan sapuan',                                2],
                        ['f', 'Ice cream',                                      'Ais krim',                                              2],
                        ['g', 'Cincau / red bean paste / black sesame',         'Cincau / pes kacang merah / bijan hitam',               2],
                        ['h', 'Cendol gula melaka',                             'Cendol gula melaka',                                    2],
                        ['i', 'Flavoured sauce / syrup',                        'Sirap / sos',                                           2],
                        ['j', 'Coarse sugar',                                   'Gula kasar',                                            2],
                        ['k', 'Longan and lychee syrup and fruit',              'Sirap dengan buah longan / laici',                      2],
                        ['l', 'Flavoured powder',                               'Serbuk berperisa',                                      2],
                        ['m', 'Kaya',                                           'Kaya',                                                  2],
                        ['n', 'Fillings',                                       'Inti',                                                  2],
                        ['o', 'Juice',                                          'Jus',                                                   2],
                        ['p', 'Omega egg',                                      'Telur omega',                                           2],
                        self::others('q'),
                    ]],
                    ['10', 'Products prepared in different batches are kept separately', 'Produk dari batch yang berlainan disimpan secara berasingan', 4],
                    ['11', 'Condiments are in fresh condition', 'Bahan sampingan dalam keadaan segar dan baik', 0, [
                        ['a', 'Lemon / lime',                                   'Lemon / limau',                                         2],
                        ['b', 'Mint leaves / pandan leaves',                    'Daun pudina / daun pandan',                             2],
                        ['c', 'Goji berry',                                     'Buah goji',                                             2],
                        ['d', 'Chocolate',                                      'Coklat',                                                2],
                        self::others('e'),
                    ]],
                    ['12', 'No excessive liquefied egg found', 'Tidak terdapat lebihan telur cair mentah', 4],
                    ['13', 'Frozen products are thawed in the chiller', 'Produk beku dicairkan di dalam peti sejuk', 4],
                    ['14', 'Bar area in good and clean condition', 'Kawasan bar dalam keadaan baik dan bersih', 0, [
                        ['a', 'Floor',                                          'Lantai',                                                2],
                        ['b', 'Wall / plug point / socket',                     'Dinding / palam / soket',                               2],
                        ['c', 'Light / fan',                                    'Lampu / kipas',                                         2],
                        ['d', 'Ceiling',                                        'Siling',                                                2],
                    ]],
                    ['15', 'Bar towel is clean and available at each station, without offensive smell', 'Tuala bar dalam keadaan bersih dan ada pada setiap stesen, tidak berbau busuk', 4],
                    ['16', 'All products / ingredients are available for customer orders', 'Semua produk / bahan sedia ada untuk pesanan pelanggan', 4],
                    ['17', 'Store room', 'Bilik simpanan', 0, [
                        ['a', 'Products are arranged by category and storage instruction. Shelves are labelled by item', 'Produk disusun mengikut kategori dan arahan penyimpanan. Rak dilabelkan mengikut item', 2],
                        ['b', 'Equipment and utensils are in good and clean condition', 'Peralatan dan perkakas berada dalam keadaan baik dan bersih', 2],
                        ['c', 'Floor / wall / door / light / ceiling / window are clean and in good condition', 'Lantai / dinding / pintu / lampu / siling / tingkap dalam keadaan bersih dan baik', 2],
                    ]],
                    ['18', 'Chemical / cleaning tools room', 'Bilik bahan kimia / peralatan pembersihan', 2],
                    ['19', 'Staff belongings', 'Barang kakitangan', 4],
                    self::productSlot('T1', 'Toast 1', 'Roti bakar 1'),
                    self::productSlot('T2', 'Toast 2', 'Roti bakar 2'),
                    self::productSlot('T3', 'Toast 3', 'Roti bakar 3'),
                    self::productSlot('H1', 'Hot drink 1', 'Minuman panas 1'),
                    self::productSlot('H2', 'Hot drink 2', 'Minuman panas 2'),
                    self::productSlot('C1', 'Cold drink 1', 'Minuman sejuk 1'),
                    self::productSlot('C2', 'Cold drink 2', 'Minuman sejuk 2'),
                    self::productSlot('C3', 'Cold drink 3', 'Minuman sejuk 3'),
                ],
            ],

            [
                'name'     => 'Kitchen Area',
                'name_alt' => 'Kawasan Dapur',
                'mode'     => AuditTemplateSection::MODE_AREA,
                'items'    => [
                    ['1', 'Equipment in good and clean condition', 'Peralatan dalam keadaan yang baik dan bersih', 0, [
                        ['a', 'Bain marie',                                     'Bekas air panas',                                       2, [], 'Setting: 70°C'],
                        ['b', 'Soup tank',                                      'Bekas sup',                                             2, [], 'Setting: 90°C'],
                        ['c', 'Gas stove / hot plate',                          'Dapur gas / plat panas',                                2],
                        ['d', 'Deep fryer',                                     'Penggoreng',                                            2, [], 'Setting: 180°C'],
                        ['e', 'Noodle boiler',                                  'Pencelur mee',                                          2],
                        ['f', 'Microwave',                                      'Ketuhar gelombang mikro',                               2],
                        ['g', 'Rice cooker',                                    'Pemasak nasi',                                          2],
                        ['h', 'Food warmer',                                    'Pemanas makanan',                                       2],
                        ['i', 'Exhaust hood',                                   'Hud ekzos',                                             2],
                        ['j', 'Kitchen sink',                                   'Sinki dapur',                                           2],
                        ['k', 'Preparation table / shelves rack / cabinet',     'Meja penyediaan / rak / kabinet',                       2],
                        ['l', 'Chiller',                                        'Peti sejuk',                                            2, [], 'Setting: 0°C – 4°C'],
                        ['m', 'Freezer',                                        'Peti beku',                                             2, [], 'Setting: below −18°C'],
                        ['n', 'Electric steamer',                               'Pengukus makanan',                                      2, [], 'Setting: standby 70°C, in use 110°C'],
                        ['o', 'Panini grill',                                   'Pemanggang panini',                                     2],
                        ['p', 'Digital timer',                                  'Penyukat masa digital',                                 2],
                        ['q', 'Docket holder / printer',                        'Pemegang doket / mesin cetakan',                        2],
                        ['r', 'Insect killer',                                  'Pembasmi serangga',                                     2],
                        ['s', 'Grease trap',                                    'Perangkap minyak',                                      2],
                        ['t', 'Foot-pedal trash bin lined with a garbage bag and properly covered', 'Tong sampah pijak kaki mempunyai beg sampah dan ditutup', 2],
                        ['u', 'Hand towel / hand soap / hand sanitiser dispenser', 'Bekas tuala tangan / bekas sabun tangan / bekas sanitizer tangan', 2],
                        self::others('v'),
                    ]],
                    ['2', 'Noodle boiler water is boiling when blanching ingredients', 'Air di dalam pencelur mendidih semasa digunakan untuk penceluran', 4],
                    ['3', 'Cooking oil is clean and free from food debris', 'Minyak masak yang digunakan bersih dan bebas dari sisa makanan', 4],
                    ['4', 'No raw / semi-cooked / cooked products found in the kitchen sink', 'Tiada produk mentah / separuh masak / sudah dimasak di dalam sinki dapur', 4],
                    ['5', 'Utensils are available and in good, clean condition', 'Perkakas lengkap dan dalam keadaan baik dan bersih', 0, [
                        ['a', 'Smallware',                                      'Alatan kecil',                                          2],
                        ['b', 'Stock pot / sauce pan / frying pan',             'Pot stok / periuk / kuali',                             2],
                        ['c', 'Cutting board / knives / scissors',              'Papan pemotong / pisau / gunting',                      2],
                        ['d', 'Measurement jug / weighing scale',               'Jag penyukat / penimbang',                              2],
                        ['e', 'Dishware',                                       'Peralatan pinggan mangkuk',                             2],
                        ['f', 'Bamboo basket',                                  'Bakul rotan',                                           2],
                        ['g', 'Ladles',                                         'Senduk',                                                2],
                        ['h', 'Container and cover',                            'Bekas dan penutup',                                     2],
                        ['i', 'Rice thermos / rice warmer',                     'Termos nasi / pemanas nasi',                            2],
                        ['j', 'Cutlery holder',                                 'Pemegang sudu / garpu',                                 2],
                        ['k', 'Colander',                                       'Penapis',                                               2],
                        ['l', 'Wooden utensil',                                 'Perkakas kayu',                                         2],
                        ['m', 'Brush',                                          'Berus',                                                 2],
                        self::others('n'),
                    ]],
                    ['6',  'Food / containers are clearly labelled with the correct shelf life. No potential cross-contamination observed', 'Produk / bekas dilabelkan dengan jangka hayat yang betul. Tiada potensi pencemaran silang', 6],
                    ['7',  'Canned products are transferred to an appropriate container after opening', 'Produk dalam tin disimpan di dalam bekas yang lain selepas dibuka', 4],
                    ['8',  'Products are covered when not in use, held and rotated properly (FIFO), and placed according to the stock arrangement', 'Produk ditutup apabila tidak digunakan, disimpan dan dipusingkan dengan betul (FIFO), dan diletakkan mengikut susunan stok', 6],
                    ['9',  'Different products are kept individually in separate covered containers', 'Produk yang berlainan disimpan dalam bekas bertutup secara berasingan', 4],
                    ['10', 'Frozen products are thawed in the chiller', 'Produk beku dicairkan di dalam peti sejuk', 4],
                    ['11', 'Stocks are prepared in compliance with the brand SOP', 'Stok disediakan mengikut SOP jenama', 0, [
                        ['a', 'Noodle soup stock',                              'Stok sup mee',                                          2],
                        ['b', 'Curry noodle stock',                             'Stok mee kari',                                         2],
                        ['c', 'Curry chicken stock',                            'Stok ayam kari',                                        2],
                        ['d', 'Asam laksa soup',                                'Sup asam laksa',                                        2],
                        ['e', 'Nasi lemak rice',                                'Nasi lemak',                                            2],
                        ['f', 'Flavoured rice',                                 'Nasi berperisa',                                        2],
                        ['g', 'Santan base',                                    'Bes santan',                                            2],
                        self::others('h'),
                    ]],
                    ['12', 'Products prepared in different batches are kept separately', 'Produk dari batch yang berlainan disimpan secara berasingan', 4],
                    ['13', 'Handling and storage according to guidelines', 'Pengendalian dan penyimpanan mengikut panduan', 0, [
                        ['a', 'Noodles / pasta',                                'Mee / pasta',                                           2],
                        ['b', 'Vegetables',                                     'Sayur-sayuran',                                         2],
                        ['c', 'Prawn / chicken slices',                         'Udang / hirisan ayam',                                  2],
                        ['d', 'BBQ chicken / minced chicken / fried chicken / chicken breast / marinated chicken chop', 'Ayam BBQ / ayam cincang / ayam goreng / ayam dada / ayam perap', 2],
                        ['e', 'Hard-boiled eggs / classic egg',                 'Telur rebus / telur klasik',                            2],
                        ['f', 'Prawn paste / noodle paste mix / sambal shrimp paste', 'Pes udang / campuran mee udang / pes sambal udang', 2],
                        ['g', 'Flavoured oils / sesame oil / soya sauce',       'Minyak berperisa / minyak bijan / sos soya',            2],
                        ['h', 'Fried tofu / fried fish ball / fried beancurd / seafood tofu', 'Tauhu goreng / bebola ikan goreng / tauhu makanan laut', 2],
                        ['i', 'Asam laksa paste / meehoon siam paste',          'Pes asam laksa / pes meehoon siam',                     2],
                        ['j', 'Sauces — BBQ / Hainan / ginger / abalone / noodle / cheese', 'Sos BBQ / sos Hainan / sos halia / sos abalone / sos mee / sos keju', 2],
                        ['k', 'Spring roll / curry puff / french fries / wanton / nugget / sesame ball / seafood roll', 'Popia / karipap / kentang goreng / wantan / nugget / bebola bijan / gulung makanan laut', 2],
                        ['l', 'Chicken sausage / chicken meatloaf',             'Sosej ayam / lof daging ayam',                          2],
                        ['m', 'Sambal / sambal sotong',                         'Sambal / sambal sotong',                                2],
                        ['n', 'Sauce / condiment',                              'Sos / bahan sampingan',                                 2],
                        ['o', 'Plant-based product',                            'Produk berasaskan tumbuhan',                            2],
                        self::others('p'),
                    ]],
                    ['14', 'Garnishing is fresh and shows no sign of browning', 'Bahan penghias dalam keadaan segar dan tidak layu', 4],
                    ['15', 'No excessive liquefied egg found', 'Tidak terdapat lebihan telur cair mentah', 4],
                    ['16', 'Kitchen area in good and clean condition', 'Kawasan dapur dalam keadaan baik dan bersih', 0, [
                        ['a', 'Floor',                                          'Lantai',                                                2],
                        ['b', 'Wall / plug point / socket',                     'Dinding / palam / soket',                               2],
                        ['c', 'Light / fan',                                    'Lampu / kipas',                                         2],
                        ['d', 'Ceiling',                                        'Siling',                                                2],
                        ['e', 'Door / back door / window',                      'Pintu / pintu belakang / tingkap',                      2],
                    ]],
                    ['17', 'All products / ingredients are available for customer orders', 'Semua produk / bahan sedia ada untuk pesanan pelanggan', 4],
                    ['18', 'Kitchen towel is clean and available at each station, without offensive smell', 'Tuala dapur dalam keadaan bersih dan tidak berbau busuk', 4],
                    ['19', 'Kitchen doorway canvas in good and clean condition', 'Kanvas pada pintu dapur dalam keadaan baik dan bersih', 2],
                    self::productSlot('N1', 'Noodle dish 1', 'Hidangan mee 1'),
                    self::productSlot('N2', 'Noodle dish 2', 'Hidangan mee 2'),
                    self::productSlot('N3', 'Noodle dish 3', 'Hidangan mee 3'),
                    self::productSlot('R1', 'Rice dish 1',   'Hidangan nasi 1'),
                    self::productSlot('R2', 'Rice dish 2',   'Hidangan nasi 2'),
                    self::productSlot('O1', 'Other dish',    'Hidangan lain'),
                ],
            ],

            [
                'name'     => 'Service Area',
                'name_alt' => 'Kawasan Servis',
                'mode'     => AuditTemplateSection::MODE_AREA,
                'items'    => [
                    ['1', 'Equipment in good and clean condition', 'Peralatan dalam keadaan yang baik dan bersih', 0, [
                        ['a', 'Air conditioning',                               'Penghawa dingin',                                       2],
                        ['b', 'Air curtain',                                    'Tirai udara',                                           2],
                        ['c', 'Audio system',                                   'Sistem audio',                                          2],
                        ['d', 'Cash register / POS',                            'Mesin kira / POS',                                      2],
                        ['e', 'CCTV',                                           'CCTV',                                                  2],
                        self::others('f'),
                    ]],
                    ['2', 'Landscape and potted plants are well maintained and clean', 'Lanskap dan tanaman berpasu dijaga dengan baik dan bersih', 4],
                    ['3', 'Sidewalk / trash area / exterior premise is well maintained and clean', 'Laluan pejalan kaki / kawasan pembuangan sampah / persekitaran luar dijaga dengan baik dan bersih', 4],
                    ['4', 'Table setting is correct and in clean, good condition', 'Aturan meja betul dan dalam keadaan bersih', 0, [
                        ['a', 'Condiment holder / order chit / pencil',         'Pemegang bahan sampingan / borang pesanan / pensel',    2],
                        ['b', 'POP material / tent card',                       'Bahan POP / kad tent',                                  2],
                        ['c', 'Menu',                                           'Menu',                                                  2],
                        ['d', 'Tissue box',                                     'Bekas tisu',                                            2],
                    ]],
                    ['5', 'Takeaway packaging materials', 'Bahan pembungkusan bawa balik', 2],
                    ['6', 'Service towel is clean and available, without offensive smell', 'Tuala servis dalam keadaan bersih dan tidak berbau busuk', 4],
                    ['7', 'Cutlery / cutlery holder / serving trays are clean and dry', 'Kutleri / pemegang kutleri / dulang servis dalam keadaan bersih dan kering', 4],
                    ['8', 'Furniture and fittings are in good and clean condition', 'Perabot dan peralatan dalam keadaan baik dan bersih', 0, [
                        ['a', 'Bar counter top',                                'Atas kaunter bar',                                      2],
                        ['b', 'Menu board',                                     'Papan menu',                                            2],
                        ['c', 'Picture frame',                                  'Bingkai gambar',                                        2],
                        ['d', 'Mirror',                                         'Cermin',                                                2],
                        ['e', 'Glass panel',                                    'Panel kaca',                                            2],
                        ['f', 'Wood panel',                                     'Panel kayu',                                            2],
                        ['g', 'Tables / chairs / cushion seats',                'Meja / kerusi / kerusi kusyen',                         2],
                        ['h', 'Baby chairs',                                    'Kerusi bayi',                                           2],
                        ['i', 'Signage — only brand signage in use',            'Penanda — hanya penanda jenama sahaja digunakan',       2],
                        ['j', 'Service station / condiments holder / cabinet', 'Stesen servis / bekas bahan sampingan / kabinet',       2],
                        ['k', 'Cashier counter',                                'Kaunter juruwang',                                      2],
                        ['l', 'Umbrella basket',                                'Bekas payung',                                          2],
                        ['m', 'Insect killer',                                  'Pembasmi serangga',                                     2],
                        ['n', 'Straw dispenser / holder',                       'Pengagih penyedut minuman / bekas',                     2],
                        ['o', 'Floor carpet',                                   'Karpet lantai',                                         2],
                        ['p', 'Menu standee',                                   'Menu standee',                                          2],
                        ['q', 'Call bell system',                               'Sistem panggilan',                                      2],
                        ['r', 'Service trolley',                                'Troli servis',                                          2],
                        ['s', 'Trash bin lined with a garbage bag; garbage not overflowing', 'Tong sampah mempunyai beg sampah; sampah tidak melimpah', 2],
                        self::others('t'),
                    ]],
                    ['9', 'Dining area in good and clean condition', 'Ruang makan dalam keadaan baik dan bersih', 0, [
                        ['a', 'Floor',                                          'Lantai',                                                2],
                        ['b', 'Wall / pillar / plug point / socket',            'Dinding / tiang / palam / soket',                       2],
                        ['c', 'Light / fan',                                    'Lampu / kipas',                                         2],
                        ['d', 'Ceiling',                                        'Siling',                                                2],
                        ['e', 'Door / door frame',                              'Pintu / bingkai pintu',                                 2],
                        ['f', 'Bus-stop light',                                 'Lampu bus stop',                                        2],
                        ['g', 'Buntings / hiring poster',                       'Bunting / poster jawatan kosong',                       2],
                    ]],
                    ['10', 'Retail display rack items are available, arranged by category with price tags in place; rack in good, clean condition', 'Rak pameran barangan runcit boleh didapati dan disusun mengikut kategori dengan tag harga; rak dalam keadaan baik dan bersih', 4],
                    ['11', 'All fire extinguishers within valid date. First aid kit available and stocked with compulsory items', 'Semua alat pemadam api dalam tempoh sah. Peti bantuan kecemasan dipenuhi dengan barangan wajib', 4],
                    ['12', 'Business operation hours observed', 'Waktu perniagaan dipatuhi', 4],
                    ['13', 'Service excellence', 'Servis', 0, [
                        ['a', 'Greet',                                          'Sambutan',                                              2],
                        ['b', 'Order',                                          'Pesanan',                                               2],
                        ['c', 'Serve',                                          'Layanan',                                               2],
                        ['d', 'Thanks',                                         'Ucapan terima kasih',                                   2],
                    ]],
                    ['14', 'Restroom area in good and clean condition', 'Kawasan tandas dalam keadaan baik dan bersih', 0, [
                        ['a', 'Hand sink',                                      'Sinki',                                                 5],
                        ['b', 'Hand soap dispenser',                            'Pengagih sabun tangan',                                 5],
                        ['c', 'Hand dryer / paper towel dispenser',             'Pengering tangan / pengagih tisu kertas',               5],
                        ['d', 'Toilet / urinals',                               'Tandas / urinal',                                       5],
                        ['e', 'Exhaust fan',                                    'Kipas ekzos',                                           5],
                        ['f', 'Floor / wall / door / ceiling / window',         'Lantai / dinding / pintu / siling / tingkap',           5],
                        ['g', 'Mirror',                                         'Cermin',                                                5],
                        ['h', 'Lights',                                         'Lampu',                                                 5],
                        ['i', 'Trash bin lined with a garbage bag; garbage not overflowing', 'Tong sampah mempunyai beg sampah; sampah tidak melimpah', 5],
                    ]],
                ],
            ],

            [
                'name'     => 'Profile and Documentation',
                'name_alt' => 'Profil dan Dokumentasi',
                'mode'     => AuditTemplateSection::MODE_AREA,
                'items'    => [
                    ['1', 'Profile', 'Profil', 0, [
                        ['a', 'Valid business licence / food premise registration certificate or slip', 'Lesen perniagaan yang sah / sijil atau slip pendaftaran premis makanan', 3],
                        ['b', 'Original halal certificate',                     'Sijil halal asal',                                      3],
                        ['c', 'Original halal logo sticker',                    'Pelekat logo halal asal',                               3],
                    ]],
                    ['2', 'Documentation — completed and updated halal file', 'Dokumentasi — fail halal yang lengkap dan dikemas kini', 5],
                    ['3', 'Copy of the previous audit / internal halal audit report', 'Salinan laporan audit terdahulu / audit dalaman halal', 3],
                    ['4', 'Previous audit / internal halal audit corrective action report', 'Laporan tindakan pembetulan audit terdahulu / audit dalaman halal', 3],
                ],
            ],
        ];
    }
}
