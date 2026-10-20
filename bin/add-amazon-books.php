<?php
declare(strict_types=1);

/**
 * Adds the Kindle titles listed on Louis Friend's Amazon author page as books (safe to run again).
 * Only the titles are filled in: no covers, descriptions, prices or product links are invented.
 * Each Kindle edition's button points to the author page; replace it with the exact product link in the CMS (Editions).
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Support\Db;

const AUTHOR_PAGE = 'https://www.amazon.com/stores/Louis-Friend/author/B007IJ8WAQ/allbooks';
$titles = ['Meeting Max', 'The Girl in the Mirror', 'The Other Man', 'Dollar Store Domme', 'Doing the Chores', 'Class Reunion', 'Satin Met a Laddie', 'Francesca', "Doctor's Orders"];

$pdo = Db::pdo();
$find = $pdo->prepare('SELECT id FROM books WHERE slug = ?');
$add = $pdo->prepare("INSERT INTO books (slug, title, description_html, status, sort_order, created_at, updated_at) VALUES (?, ?, '', 'published', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())");
$ed = $pdo->prepare("INSERT INTO book_editions (book_id, name, purchase_url, sort_order) VALUES (?, 'Kindle Edition', ?, 0)");
$added = 0;
foreach ($titles as $i => $t) {
    $slug = slugify($t);
    $find->execute([$slug]);
    if ($find->fetchColumn()) {
        continue;
    }
    $add->execute([$slug, $t, 10 + $i]);
    $ed->execute([(int) $pdo->lastInsertId(), AUTHOR_PAGE]);
    $added++;
}

// Details copied from each book's Amazon page (blurb, ASIN, publication date, length). No prices.
$details = [
    'meeting-max' => ['B00KZAP0Q4', '2014-06-13', 15, ['A submissive man meets up with a Dominant Male (and his female friend) and gets more than he bargained for.']],
    'the-girl-in-the-mirror' => ['B00HT73QQC', '2014-01-10', 7, ['Adult Reading. I played it very slow with Sally. I relished the process of learning about her passions and her limits. This is the tale of the first time I used her bottom for my own purposes.']],
    'the-other-man' => ['B00C3N72E4', '2013-03-28', 16, ['Adult Material.', 'I\'m that guy. Some people call me "the other man." Others call me "Bull." I prefer to go by my real name, Eduardo.', 'I\'m the guy who, in the stories, is always in the bedroom with the wife or girlfriend getting it on in the afternoon when the husband or boyfriend comes home early from work.']],
    'dollar-store-domme' => ['B00BTR03Z0', '2013-03-13', 9, ['This girl likes her toys. I\'ve spent two decades amassing a very respectable collection. I keep several suitcases and duffel bags packed and waiting at all times. I\'ve got them at home, in my car, even one at work; each stocked with basic tools of my trade along with some personal favorites. I supplement these with additional toys depending on the client, of course.', 'Imagine my dismay when I arrive in Detroit for a special session with one of my old regulars only to find that the airline sent my bags back to Philadelphia, giving them a round trip without a stop in between. If you can\'t imagine it, you\'ll just have to trust me. I\'m freaking out.']],
    'doing-the-chores' => ['B00BLFD370', '2013-02-25', 10, ['I thought moving back home after college was the most humiliating thing that had happened to me but that would pale in comparison to what occurred next.', 'I had spent the summer on my own in an apartment, barely eking out a living at a minimum wage job while sending out letters of inquiry to anything even remotely related to my major. I finally managed to find an internship and, as it was unpaid, I knew I couldn\'t support myself. Thus, the move back home with my mom and my step-dad, Jerry.']],
    'class-reunion' => ['B00BJEBLOK', '2013-02-21', 13, ['When visiting a class reunion, a past love is rekindled and old secrets are revealed.']],
    'satin-met-a-laddie' => ['B00BIW8MF4', '2013-02-20', 8, ['Satin unlocked in him something buried deep inside. The silky touch of that wondrous material made his skin sing and his nerves burn with delightful yearning.'], '978-1301430246'],
    'francesca' => ['B00BCUKDAE', '2013-02-07', 12, ['It\'s appropriate that the first time I saw her was from the back. Her bottom has been at the center of our relationship ever since. I took her phone, blindfolded her, and drove her to my home where I tortured and delighted the poor darling.', 'This is the tale of the first time I met Francesca, a lovely girl who loved to be dominated, used, and abused.']],
    'doctor-s-orders' => ['B00B6WUEJ8', '2013-01-27', 9, ['A man goes to a specialist about his apparent sterility for a very interesting assessment.']],
];
$upBook = $pdo->prepare("UPDATE books SET description_html = ?, subtitle = ?, published_on = ?, updated_at = UTC_TIMESTAMP() WHERE slug = ? AND description_html = ''");
// Associates tag taken from the SiteStripe link Mike supplied; his own amzn.to links are never overwritten.
const ASSOC_TAG = 'proboothcas0f-20';
$upEd = $pdo->prepare("UPDATE book_editions SET purchase_url = ?, isbn = ? WHERE book_id = ? AND name = 'Kindle Edition' AND purchase_url NOT LIKE 'https://amzn.to/%' AND purchase_url <> ?");
$filled = 0;
foreach ($details as $slug => $d) {
    $html = implode("\n", array_map(fn($p) => '<p>' . htmlspecialchars($p, ENT_NOQUOTES) . '</p>', $d[3]));
    $upBook->execute([$html, 'Kindle story · ' . $d[2] . ' pages', $d[1], $slug]);
    $find->execute([$slug]);
    $bid = (int) $find->fetchColumn();
    if ($bid) {
        $link = 'https://www.amazon.com/dp/' . $d[0] . '?tag=' . ASSOC_TAG;
        $upEd->execute([$link, $d[4] ?? '', $bid, $link]);
        $filled += $upEd->rowCount();
    }
}

// Kindle links Mike supplied for the two books that already exist (kept exactly as given).
$kindle = ['freedom-is-slavery' => 'https://amzn.to/4eHrV3P', 'life-on-my-knees' => 'https://amzn.to/4enhSRn'];
$has = $pdo->prepare('SELECT 1 FROM book_editions WHERE book_id = ? AND purchase_url = ?');
$next = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM book_editions WHERE book_id = ?');
$put = $pdo->prepare("INSERT INTO book_editions (book_id, name, purchase_url, sort_order) VALUES (?, 'Kindle Edition', ?, ?)");
$linked = 0;
foreach ($kindle as $slug => $url) {
    $find->execute([$slug]);
    $id = (int) $find->fetchColumn();
    if (!$id) {
        continue;
    }
    $has->execute([$id, $url]);
    if (!$has->fetchColumn()) {
        // the old Blogger "eBook" button already points at the Amazon Kindle book: update it rather than list it twice
        $swap = $pdo->prepare("UPDATE book_editions SET name = 'Kindle Edition', purchase_url = ? WHERE book_id = ? AND name = 'eBook' AND purchase_url LIKE '%amazon.com%'");
        $swap->execute([$url, $id]);
        if ($swap->rowCount()) {
            $linked++;
            continue;
        }
        $next->execute([$id]);
        $put->execute([$id, $url, (int) $next->fetchColumn()]);
        $linked++;
    }
}
echo "$added books added, $linked Kindle links added, $filled product links set.\n";
