@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if (trim($slot) === 'Laravel' || empty(trim($slot)))
<span style="font-size: 20px; font-weight: 800; color: #d97706; letter-spacing: -0.3px;">EMK Center</span>
<div style="font-size: 11px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">American Spaces Bangladesh</div>
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
