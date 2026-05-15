<?php
/**
 * ComparerController — Page Comparateur athletes/clubs
 */

class ComparerController extends Controller
{
    public function index()
    {
        $this->render('comparer', [
            'page' => 'comparer',
            'seo' => SeoService::build('comparer', []),
        ]);
    }
}
