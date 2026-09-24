{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
{{-- The list of public addresses, built by `App\Showcase\Sitemap`. Nothing is
     decided here: what is on it and what its date means live in that class. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($entries as $entry)
    <url>
        <loc>{{ $entry['loc'] }}</loc>
        <lastmod>{{ $entry['lastmod'] }}</lastmod>
    </url>
@endforeach
</urlset>
