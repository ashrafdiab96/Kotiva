@extends('emails.layout')

@section('subject', 'Enquiry: '.$enquiry->enquiryLabel())

@section('preheader'){{ $enquiry->name }} — {{ $enquiry->enquiryLabel() }}@endsection

@php
    $ink = '#231F20';
    $muted = 'rgba(35,31,32,0.62)';
    $hairline = 'rgba(35,31,32,0.12)';
    $accent = '#005DBA';
    $display = "'Futura', 'Century Gothic', 'Trebuchet MS', Arial, sans-serif";
@endphp

@section('content')

  <p style="margin:0 0 20px; font-family:{{ $display }}; font-size:13px; font-weight:bold; letter-spacing:0.14em; text-transform:uppercase;">
    New enquiry
  </p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
    <tr>
      <td style="border:1px solid {{ $hairline }}; padding:16px 20px;">
        <div style="font-family:{{ $display }}; font-size:9px; font-weight:bold; letter-spacing:0.2em; text-transform:uppercase; color:{{ $muted }};">Type</div>
        <div style="font-family:{{ $display }}; font-size:15px; font-weight:bold; letter-spacing:0.06em; text-transform:uppercase; color:{{ $ink }}; padding-top:5px;">
          {{ $enquiry->enquiryLabel() }}
        </div>
      </td>
    </tr>
  </table>

  <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:10px;">From</div>
  <p style="margin:0 0 22px; font-size:15px; line-height:1.8;">
    {{ $enquiry->name }}<br>
    <a href="mailto:{{ $enquiry->email }}" style="color:{{ $accent }}; text-decoration:none;">{{ $enquiry->email }}</a><br>
    <span style="font-size:14px; color:{{ $muted }};">Received {{ $enquiry->created_at?->format('j M Y, H:i') }}</span>
  </p>

  @if ($enquiry->message)
    <div style="font-family:{{ $display }}; font-size:10px; font-weight:bold; letter-spacing:0.22em; text-transform:uppercase; color:{{ $muted }}; padding-bottom:10px;">Message</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 22px;">
      <tr>
        <td style="border-left:3px solid {{ $accent }}; padding:14px 18px; background:rgba(138,183,233,0.10); font-size:15px; line-height:1.8; color:{{ $ink }};">
          {!! nl2br(e($enquiry->message)) !!}
        </td>
      </tr>
    </table>
  @endif

  <p style="margin:0; font-size:14px; color:{{ $muted }};">
    Reply to this email to answer {{ $enquiry->name }} directly.
  </p>

@endsection
