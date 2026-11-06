{{--
    Items 64–65: one public legal page — Privacy Policy, Terms and
    Conditions, Cookie Policy or Accessibility (LegalController,
    App\Support\Legal), from one of two sets: ResQPulse's (the residents'
    app and this website) or Resqponder's (the response teams' app, under
    /resqponder/). No sign-in needed. Nothing is loaded from other websites,
    so opening it sends nothing to Google or a CDN.

    Accessibility: a "Skip to the document" link, one h1 and an h2 per
    section, "On this page" links, the current document marked in the
    menu (aria-current), visible keyboard focus, and colors with at least
    4.5:1 contrast.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document['title'] }} · {{ $set['name'] }}</title>
    <meta name="description" content="{{ $document['summary'] }} — {{ $set['name'] }}, MDRRMO Rosales, Pangasinan.">
    <link rel="icon" href="{{ asset('images/logo.png') }}">
    <style>
        :root {
            --ink: #111827;
            --body: #374151;
            --muted: #4b5563;
            --navy: #1a3a8f;
            --line: #e5e7eb;
            --soft: #f5f6fa;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 1.0625rem;
            line-height: 1.65;
            color: var(--body);
            background: #fff;
        }
        a { color: var(--navy); text-underline-offset: 3px; }
        a:focus-visible, button:focus-visible {
            outline: 3px solid #f59e0b;
            outline-offset: 2px;
            border-radius: 4px;
        }
        .skip {
            position: absolute;
            left: -9999px;
            top: 0;
            background: var(--navy);
            color: #fff;
            padding: .75rem 1rem;
            z-index: 10;
        }
        .skip:focus { left: 1rem; top: 1rem; }
        header.site {
            background: #0d1b4c;
            color: #fff;
        }
        .site-inner, main, footer.site .inner {
            max-width: 760px;
            margin: 0 auto;
            padding: 0 1.25rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 1rem 0 .5rem;
            color: #fff;
            text-decoration: none;
        }
        .brand img { width: 40px; height: 40px; border-radius: 50%; background: #fff; }
        .brand strong { display: block; font-size: 1.15rem; line-height: 1.2; color: #fff; }
        .brand span { display: block; font-size: .85rem; color: #dbe3ff; line-height: 1.3; }
        nav.docs ul {
            list-style: none;
            margin: 0;
            padding: .25rem 0 0;
            display: flex;
            flex-wrap: wrap;
            gap: .25rem;
        }
        nav.docs a {
            display: inline-block;
            padding: .6rem .8rem;
            color: #fff;
            text-decoration: none;
            border-bottom: 3px solid transparent;
            font-weight: 600;
            font-size: .95rem;
        }
        nav.docs a:hover { text-decoration: underline; }
        nav.docs a[aria-current="page"] { border-bottom-color: #fbbf24; }
        .other-set {
            background: #e8edfb;
            color: #1f2937;
            font-size: .92rem;
        }
        .other-set .site-inner { padding-top: .6rem; padding-bottom: .6rem; }
        .other-set a { font-weight: 600; }
        main { padding-top: 2rem; padding-bottom: 2.5rem; }
        h1 { color: var(--ink); font-size: 2rem; line-height: 1.2; margin: 0 0 .35rem; }
        .updated { color: var(--muted); font-size: .95rem; margin: 0 0 1.25rem; }
        .toc {
            background: var(--soft);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin: 1.5rem 0 .5rem;
        }
        .toc h2 { font-size: 1rem; margin: 0 0 .4rem; color: var(--ink); }
        .toc ol { margin: 0; padding-left: 1.25rem; }
        .toc li { margin: .2rem 0; }
        section { padding-top: 1.25rem; }
        section h2 { color: var(--ink); font-size: 1.35rem; line-height: 1.3; margin: 1rem 0 .5rem; }
        section ul { padding-left: 1.25rem; margin: .25rem 0 .9rem; }
        section li { margin: .35rem 0; }
        strong { color: var(--ink); }
        .contact {
            background: var(--soft);
            border-radius: 12px;
            padding: 1.1rem 1.25rem;
            margin-top: 2rem;
        }
        .contact h2 { font-size: 1.05rem; margin: 0 0 .25rem; color: var(--ink); }
        .contact p { margin: 0; }
        footer.site { border-top: 1px solid var(--line); }
        footer.site .inner { padding-top: 1rem; padding-bottom: 1.5rem; color: var(--muted); font-size: .9rem; }
        @media (max-width: 520px) {
            h1 { font-size: 1.6rem; }
            nav.docs a { padding: .55rem .6rem; font-size: .9rem; }
        }
    </style>
</head>
<body>
<a class="skip" href="#document">Skip to the document</a>

<header class="site">
    <div class="site-inner">
        <a class="brand" href="{{ url($setKey === 'resqponder' ? 'resqponder/privacy-policy' : 'privacy-policy') }}">
            <img src="{{ asset('images/logo.png') }}" alt="">
            <span>
                <strong>{{ $set['name'] }}</strong>
                <span>{{ $setKey === 'resqponder' ? 'For MDRRMO Rosales response teams' : 'MDRRMO Rosales, Pangasinan' }}</span>
            </span>
        </a>
        <nav class="docs" aria-label="{{ $set['name'] }} legal documents">
            <ul>
                @foreach ($documents as $docPath => $doc)
                    <li>
                        <a href="{{ url($docPath) }}" @if ($docPath === $path) aria-current="page" @endif>{{ $doc['title'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</header>
{{-- Item 65: each app has its own documents. --}}
<div class="other-set">
    <div class="site-inner">
        @if ($setKey === 'resqponder')
            These are Resqponder's documents, for response teams. Residents and the website:
            <a href="{{ url($otherPath) }}">ResQPulse's documents</a>.
        @else
            Response teams using the Resqponder app:
            <a href="{{ url($otherPath) }}">Resqponder's documents</a>.
        @endif
    </div>
</div>

<main id="document" tabindex="-1">
    <h1>{{ $document['title'] }}</h1>
    <p class="updated">Last updated: {{ $set['updated'] }}</p>

    @foreach ($document['intro'] as $paragraph)
        <p>{{ \App\Support\Legal::inline($paragraph) }}</p>
    @endforeach

    <nav class="toc" aria-labelledby="toc-title">
        <h2 id="toc-title">On this page</h2>
        <ol>
            @foreach ($document['sections'] as [$heading, $blocks])
                <li><a href="#{{ \Illuminate\Support\Str::slug($heading) }}">{{ $heading }}</a></li>
            @endforeach
        </ol>
    </nav>

    @foreach ($document['sections'] as [$heading, $blocks])
        <section aria-labelledby="{{ \Illuminate\Support\Str::slug($heading) }}">
            <h2 id="{{ \Illuminate\Support\Str::slug($heading) }}">{{ $heading }}</h2>
            @foreach (\App\Support\Legal::blocks($blocks) as $block)
                @if ($block['type'] === 'ul')
                    <ul>
                        @foreach ($block['items'] as $item)
                            <li>{{ \App\Support\Legal::inline($item) }}</li>
                        @endforeach
                    </ul>
                @else
                    <p>{{ \App\Support\Legal::inline($block['text']) }}</p>
                @endif
            @endforeach
        </section>
    @endforeach

    <div class="contact">
        <h2>Questions?</h2>
        <p>Call MDRRMO Rosales at <a href="tel:%232441">#2441</a>, or visit the MDRRMO office.</p>
    </div>
</main>

<footer class="site">
    <div class="inner">
        {{ $set['name'] }} is run by the Municipal Disaster Risk Reduction and Management Office (MDRRMO) of Rosales, Pangasinan.
        · <a href="{{ route('login') }}">MDRRMO staff sign-in</a>
    </div>
</footer>
</body>
</html>
