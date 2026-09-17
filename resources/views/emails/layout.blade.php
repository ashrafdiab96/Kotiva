{{--
  KOTIVA transactional email shell.

  Hand-written table layout with inline styles, because that is what email
  clients actually render: Outlook ignores <style> blocks in <head>, Gmail
  strips much of the rest, and flexbox/grid are unavailable. Everything the
  brand needs survives that — the wordmark, the bronze/blue accent, the
  uppercase display treatment for labels.

  Fonts: the self-hosted brand faces cannot be loaded in email, so this falls
  back to a system stack chosen to keep the same feel (geometric sans for
  headings, serif for body) rather than pretending the webfont is available.

  RESERVED NAME: never pass view data called `message` to a mail view. Laravel
  injects its own $message (an Illuminate\Mail\Message) into every one, which
  silently shadows yours and throws when Blade tries to escape it.
--}}
@php
    $accent = '#005DBA';
    $ink = '#231F20';
    $muted = 'rgba(35,31,32,0.62)';
    $hairline = 'rgba(35,31,32,0.12)';
    $display = "'Futura', 'Century Gothic', 'Trebuchet MS', Arial, sans-serif";
    $body = "Georgia, 'Times New Roman', serif";
    $origin = rtrim((string) config('kotiva.site_origin'), '/');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="x-apple-disable-message-reformatting">
  <title>@yield('subject', 'KOTIVA')</title>
</head>
<body style="margin:0; padding:0; background:#FFFFFF; color:{{ $ink }};">

  {{-- Preheader: the grey line a client shows next to the subject. Hidden in
       the body itself, so it never renders twice. --}}
  <div style="display:none; max-height:0; overflow:hidden; opacity:0;">@yield('preheader')</div>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#FFFFFF;">
    <tr>
      <td align="center" style="padding:32px 16px;">

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%;">

          <!-- Wordmark -->
          <tr>
            <td align="center" style="padding:0 0 28px;">
              <a href="{{ $origin }}" style="text-decoration:none; color:{{ $ink }};">
                <span style="font-family:{{ $display }}; font-size:24px; font-weight:bold; letter-spacing:0.28em; text-transform:uppercase;">kotiva</span>
              </a>
              <div style="font-family:{{ $display }}; font-size:9px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-top:8px;">
                Doctor-Approved Skincare
              </div>
            </td>
          </tr>

          <!-- Accent rule -->
          <tr><td style="border-top:2px solid {{ $accent }}; font-size:0; line-height:0;">&nbsp;</td></tr>

          <!-- Body -->
          <tr>
            <td style="padding:32px 0 8px; font-family:{{ $body }}; font-size:16px; line-height:1.75; color:{{ $ink }};">
              @yield('content')
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:32px 0 0; border-top:1px solid {{ $hairline }};">
              <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.16em; text-transform:uppercase; color:{{ $muted }}; line-height:1.9;">
                &copy; {{ date('Y') }} KOTIVA&trade;, a brand of Vitakode LLC
              </div>
              <div style="font-family:{{ $body }}; font-size:13px; color:{{ $muted }}; padding-top:10px; line-height:1.7;">
                Questions? Reply to this email or visit
                <a href="{{ $origin }}/contact" style="color:{{ $accent }}; text-decoration:none;">kotiva.co/contact</a>.
              </div>
              @yield('footer')
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
