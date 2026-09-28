@extends('emails.layout')

@section('subject', 'Welcome to KOTIVA')

@section('preheader')
Ingredient breakdowns, routine guides and clinical updates — no filler.
@endsection

@php
    $ink = '#231F20';
    $muted = 'rgba(35,31,32,0.62)';
    $display = "'Futura', 'Century Gothic', 'Trebuchet MS', Arial, sans-serif";
    $origin = rtrim((string) config('kotiva.site_origin'), '/');
@endphp

@section('content')

  <p style="margin:0 0 20px; font-size:17px;">You're subscribed. Welcome to the standard.</p>

  {{-- The same promise the signup form makes on the site. Saying something
       different here would be the first thing this list got wrong. --}}
  <p style="margin:0 0 24px;">
    Ingredient breakdowns, routine guides and clinical updates — no filler.
    We write when there is something worth reading, not on a schedule.
  </p>

  <p style="margin:0 0 28px;">
    A good place to start is the Routine Finder: seven short questions, and it builds a
    morning and evening routine from our range, explaining why it chose each product.
  </p>

  <p style="margin:0 0 28px;">
    <a href="{{ $origin }}/routine-finder" style="display:inline-block; padding:14px 28px; background:{{ $ink }}; color:#FFFFFF; text-decoration:none; font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.18em; text-transform:uppercase;">Find My Routine</a>
  </p>

@endsection

@section('footer')
  <div style="font-family:{{ $display }}; font-size:9px; font-weight:600; letter-spacing:0.14em; text-transform:uppercase; color:{{ $muted }}; padding-top:12px;">
    You are receiving this because {{ $subscriber->email }} signed up at kotiva.co.
  </div>
@endsection
