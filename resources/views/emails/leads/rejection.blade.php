<x-mail::message>
# Membership Application Status Update

Dear **{{ $applicantName }}**,

Thank you for your interest in joining the American Spaces Bangladesh network and submitting an application for membership at {{ $targetSpace }} (Reference ID: `{{ $referenceCode }}`).

Following a comprehensive review by our admissions management, we regret to inform you that we are unable to approve your application at this time.

### Review Finding:
> {{ $applicantFacingMessage }}

---

### Reapplication Guidance:
If you wish to reapply, you are welcome to submit a fresh application through our public membership portal with compliant documentation and a new bKash fee payment reference.

Before reapplying, please ensure:
* All uploaded personal identification documents are clear, valid, and unexpired.
* Photographs meet our studio passport requirements (solid white background, direct frontal portrait, no eyewear, ears visible).
* For applicants under 18 years of age, a completed and signed Parental/Guardian Consent Form is attached.
* The bKash Transaction ID entered corresponds to a fresh, dedicated fee deposit for the new application.

If you believe this determination was reached in error or need further clarification, please contact our Information Desk at **info@emk.com.bd** with your application reference code (`{{ $referenceCode }}`).

Sincerely,  
**Workspace Admissions Management**  
EMK Center & American Spaces Bangladesh
</x-mail::message>
