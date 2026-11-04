<x-mail::message>
# Security Alert: Member Email Change Requested

A member has submitted an official request to update their registered email address via the EMK Member Portal.

Because email addresses serve as the primary authentication credential and official correspondence endpoint, this request requires Workspace Manager authorization before any changes are applied.

### Request Details:
* **Member Name:** {{ $memberName }}
* **Member ID:** `{{ $memberId }}`
* **Current Registered Email:** `{{ $currentEmail }}`
* **Proposed New Email:** `{{ $newEmail }}`
* **Requested Timestamp:** {{ $timestamp }}

### Submitted Justification:
> {{ $justification }}

---

Please review this change request in the Member Change Requests Directory. Approving this request will atomically synchronize both the member profile and their member portal login credentials.

<x-mail::button :url="$actionUrl">
Review Email Change Request
</x-mail::button>

*(If the button above does not work, visit: `{{ $actionUrl }}`)*

Warm regards,  
**EMK Center Security & Membership Team**  
American Spaces Bangladesh
</x-mail::message>
