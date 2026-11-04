<x-mail::message>
# Action Required: Outstanding Application Items

Dear **{{ $applicantName }}**,

Thank you for your application for membership at American Spaces Bangladesh ({{ $targetSpace }}).

During the verification review of your application (Reference ID: `{{ $referenceCode }}`), our admissions team identified outstanding or unreadable items that require your attention before we can proceed with your membership induction.

### Outstanding Items Required:
@foreach($missingItems as $item)
* **{{ $item }}**
@endforeach

@if(!empty($customInstructions))
### Staff Review Instructions:
{{ $customInstructions }}
@endif

---

### What You Need To Do:
Please respond directly to this email or visit the EMK Center front desk with clear, high-resolution replacement scans or documentation matching the requirements listed above.

* **Identity Documents:** Scans must be uncropped, in focus, and clearly show your full name, date of birth, and identity number.
* **Studio Photo:** Must have a solid plain white background with direct frontal view, both ears visible, and no eyeglasses or sunglasses.
* **Parental Consent:** For applicants under 18 years of age, a signed Parental/Guardian Consent Form is mandatory.

If you have any questions or require assistance, please contact the Information Desk at **info@emk.com.bd** quoting your reference code (`{{ $referenceCode }}`).

Warm regards,  
**EMK Center Admissions & Membership Team**  
American Spaces Bangladesh
</x-mail::message>
