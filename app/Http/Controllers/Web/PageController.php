<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

/**
 * Guest/public content pages. Packages, Gallery, FAQs, and Contact are
 * currently backed by config files (config/packages.php, gallery.php,
 * faqs.php, contact_info.php) rather than the database — see the doc
 * comment at the top of each config file for why and how to migrate it
 * to Admin-managed content later. About is static page copy with no
 * temporary-content concerns.
 */
class PageController extends Controller
{
    public function packages()
    {
        return view('pages.packages', [
            'featured' => config('packages.featured'),
            'items' => config('packages.items'),
            'addOns' => config('packages.add_ons'),
            'tagline' => config('packages.tagline'),
            'disclaimer' => config('packages.disclaimer'),
        ]);
    }

    public function about()
    {
        return view('pages.about');
    }

    public function gallery()
    {
        return view('pages.gallery', [
            'categories' => config('gallery.categories'),
        ]);
    }

    public function faqs()
    {
        return view('pages.faqs', [
            'faqs' => config('faqs.categories'),
        ]);
    }

    public function contact()
    {
        return view('pages.contact', [
            'info' => config('contact_info'),
        ]);
    }
}
