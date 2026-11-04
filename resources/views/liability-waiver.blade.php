@php
	$newOrEdit = $bookingInfo->liability_waiver_signing == '' ? 'New' : 'Edit';
	$submitRoute = 'customer';
@endphp

@extends('layouts.defaulmailLink')

@section('content')
<section>
	<h2 class="text-center">{{ $bookingInfo->tour_package }} &bull; Liability Waiver</h2>
	<hr>

	<div>{{-- $bookingInfo->liability_waiver_text --}}
		<?php
		if($bookingInfo->waiver_content!=''){
			echo $bookingInfo->waiver_content;
		}else{ ?>
		<p align="center">Participant Release and Waiver of Liability</p>
		<p align="center">This Participant Release and Waiver of Liability (“Agreement”) is made between Lower Antelope Canyon Tours, LLC, located at Indian Route 222, LeChee, Arizona 86040 (“Company”) and <input style="outline: none; border: 0; border-bottom: 1px solid; min-width: 160px; display: inline-block;" type="hidden" name="username"> (“I” or “me”).</p>
		<h2 align="center">RECITALS</h2>
		<p>This Agreement sets forth the terms and conditions whereby Company agrees to provide services to participants. This Agreement clarifies the participant’s assumption of risk in using the Company’s services and facilities.</p>
		<p>The parties agree as follows:</p>

		<h3>1. RELEASE OF LIABILITY AND ASSUMPTION OF RISK</h3>
		<p><b>1.1</b> The individual named above (referred to as “I” or “me”, which terms shall also include individual's parents or guardian if individual is under 18 years of age) desires to participate in hiking or other activities Company provides (“Activities”).</p>
		<p><b>1.2</b> If I am under 18 years of age, this Agreement will be entered into by my parent or guardian. As the parent or legal guardian of the minor, I agree to the terms set forth in this Agreement and authorize my minor’s participation in the Activities.</p>
		<p><strong>1.3 I AM VOLUNTARILY PARTICIPATING IN THE AFOREMENTIONED ACTIVITIES AND I AM PARTICIPATING IN THE ACTIVITIES ENTIRELY AT MY OWN RISK. I AM AWARE OF THE RISKS ASSOCIATED WITH PARTICIPATING IN THE ACTIVITIES, WHICH MAY INCLUDE, BUT ARE NOT LIMITED TO: PHYSICAL OR PSYCHOLOGICAL INJURY, PAIN, SUFFERING, ILLNESS, HOSPITALIZATION, INFECTIONS, INSECT AND ANIMAL BITES, DISFIGUREMENT, TEMPORARY OR PERMANENT DISABILITY (INCLUDING PARALYSIS), ECONOMIC OR EMOTIONAL LOSS, AND DEATH. I UNDERSTAND THAT THESE INJURIES OR OUTCOMES MAY ARISE FROM MY OWN OR OTHERS' NEGLIGENCE, CONDITIONS RELATED TO TRAVEL TO AND FROM THE ACTIVITIES, OR FROM CONDITIONS AT THE ACTIVITIES’ LOCATION(S). NONETHELESS, I ASSUME ALL RELATED RISKS, BOTH KNOWN AND UNKNOWN TO ME, OF MY PARTICIPATION IN THE ACTIVITIES. THIS SPECIFICALLY INCLUDES ANY AND ALL RISKS ASSOCIATED WITH THE VIRUS, “COVID19”.</strong></p>
		<p><b>1.4</b> I hereby, for myself, my heirs, executors, administrators, assigns, or personal representatives, knowingly and voluntarily enter into this Agreement and hereby expressly waive and release any and all rights, claims, or causes of action of any kind arising out of my participation in the Activities, now known or unknown, against the Company, and its officers, directors, employees, agents, affiliates, successors, and assigns (collectively, “Releasees”), on account of injury, death, or property damage arising out of my actions, or the negligence of the Company or any Releasee, and forever release and discharge the Company and all other Releasees from liability under such claims. This Agreement extends to any and all claims of every kind or nature whatsoever, foreseen or unforeseen, known or unknown, including but not limited to any physical or psychological injury that I may suffer as a direct result of my participation in the Activities.</p>
		<p><b>1.5</b> I further agree to indemnify, defend, and hold harmless the Company and all other Releasees against any and all losses, injuries, damages, liabilities, deficiencies, claims, actions, judgments, settlements, interest, awards, penalties, fines, costs, or expenses of whatever kind, including attorney fees, fees and the costs of enforcing any right to indemnification under this Agreement, and the cost of pursuing any insurance providers, arising out or resulting from any claim of a third party related to the Activities.</p>
		<p><b>1.6</b> I further acknowledge that Releasees are not responsible for errors, omissions, acts, or failures to act of any party or entity conducting a specific event or activity on behalf of Releasees. In the event that I should require medical care or treatment, I authorize Company to provide all emergency medical care deemed necessary, including but not limited to, first aid, CPR, the use of AEDs, emergency medical transport, and sharing of medical information with medical personnel. I further agree to assume all costs involved and agree to be financially responsible for any costs incurred as a result of such treatment. I am aware and understand that I should carry my own health insurance.</p>
		<br>

		<h3>2. WARRANTIES</h3>
		<p><b>2.1</b> I, the undersigned, for myself, personal representatives, executors, heirs, next of kin or anyone else who might make a claim on my behalf:</p>
		<ul style="list-style: none;">
			<li style="margin-bottom: 10px;">(a) Acknowledge, agree, and represent that I will use due caution for my safety and the safety of others when entering the area or areas in use by the Company;</li>
			<li style="margin-bottom: 10px;">(b) Hereby assume all responsibility for any risk of bodily injury, death, or property damage arising out of or related to my access and use of the Company’s services or facilities; and</li>
			<li style="margin-bottom: 10px;">(c) Hereby execute this Agreement voluntarily and without any inducement and I accept of my own free will.</li>
		</ul>
		<br>

		<h3>3. AGREEMENT TO OFFICIAL RULES</h3>
		<p><b>3.1</b> I fully and unconditionally agree to and accept the Official Rules of the Company. By using the Company’s services and facilities, I waive any right to claim ambiguity in these Official Rules. The failure or delay by the Company in enforcing any particular clause, provision, or aspect of these Official Rules will not impact the validity, enforceability, or effect of such clause, provision or aspect, or any other clause, provision, or aspect of these Official Rules. </p>
		<p><b>3.2</b> Company’s Official Rules shall be governed by the Official Rules of the Company attached hereto as <strong style="text-decoration: underline;">Exhibit A</strong>, and incorporated herein by referenced.</p>
		<p hidden>Initials:<input style="outline: none; border: 0; border-bottom: 1px solid; width: 100px; display: inline-block;" type="hidden" name="username"></p>
		<br>

		<h3>4. PERSONAL INJURIES</h3>
		<p><b>4.1</b> I further acknowledge that the Activities may involve a test of a person's physical and mental limits and may carry with it the potential for death, serious injury, and property loss. I agree not to participate in the Activities unless I am physically and medically capable and properly trained. I agree to abide by any decision of a Company official or agent regarding my approval to participate in the Activities. I further agree that Company officials or volunteers may authorize necessary emergency treatment for me as described in Section 1.6.</p>
		<p hidden>Initials:<input style="outline: none; border: 0; border-bottom: 1px solid; width: 100px; display: inline-block;" type="hidden" name="username"></p>
		<br>

		<h3>5. STOLEN ITEMS OR PROPERTY</h3>
		<p><b>5.1</b> Participant Release and Waiver of Liability. I will not bring items of extraordinary value to the Company. It is my responsibility to secure my items properly</p>
		<p><b>5.2</b> The Company and its Releasees will not be held liable for any lost or stolen articles and will not be held liable to replace any lost or stolen articles.</p>
		<p hidden>Initials:<input style="outline: none; border: 0; border-bottom: 1px solid; width: 100px; display: inline-block;" type="hidden" name="username"></p>
		<br>

		<h3>6. PUBLICITY</h3>
		<p><b>6.1</b> I agree to allow the Company and its Releasees, to use my name, likeness, and other personal characteristics in connection with the Activities and all other events of the Company, for any purpose related to advertising, promotion, and other commercial and business purposes of the Company worldwide in perpetuity in all forms of media now and forever known.</p>
		<p hidden>Initials:<input style="outline: none; border: 0; border-bottom: 1px solid; width: 100px; display: inline-block;" type="hidden" name="username"></p>
		<br>

		<h3>7. GENERAL PROVISIONS</h3>
		<p><b>7.1</b> To the extent that statute or case law does not prohibit releases for ordinary negligence, this release is also for such negligence on the part of Company, its agents, and its employees.</p>
		<p><b>7.2</b> Participant Liability. In the event that any damage to equipment or facilities occurs as a result of my or my family's or my agent's willful actions, neglect, or recklessness, I acknowledge and agree to be held liable for any and all costs associated with any such actions of neglect or recklessness.</p>
		<p><b>7.3</b> Entire Agreement. This Agreement constitutes the sole and entire agreement of the Company and me with respect to the subject matter contained herein and supersedes all prior and contemporaneous understandings, agreements, representations, and warranties, both written and oral, with respect to such subject matter.</p>
		<p><b>7.4</b> Partial Invalidity. In the event that any provision contained within this Agreement shall be deemed to be severable or invalid, or if any term, condition, phrase or portion of this Agreement shall be determined to be unlawful or otherwise unenforceable, the remainder of this Agreement shall remain in full force and effect. If a court should find that any provision of this Agreement to be invalid or unenforceable, but that by limiting said provision it would become valid and enforceable, then said provision shall be deemed to be written, construed and enforced as so limited.</p>
		<p><b>7.5</b> Jurisdiction and Venue. All matters arising out of or relating to this Agreement will be governed by and construed in accordance with the internal laws of the State of Arizona without giving effect to any choice or conflict of law provision or rule (whether of the State of Arizona or any other jurisdiction). Any claim or cause of action arising under this Agreement may be brought only in the federal and state courts located in Maricopa County, Arizona and I hereby consent to the exclusive jurisdiction of such courts.</p>
		<p><b>7.6</b> Plain Language. I agree that this Agreement is clear and unambiguous as to its terms, and that no other evidence shall be used or admitted to alter or explain the terms of this Agreement, but that it will be interpreted based on the plain language in accordance with the purposes for which it is entered into.</p>
		<p hidden>Initials:<input style="outline: none; border: 0; border-bottom: 1px solid; width: 100px; display: inline-block;" type="hidden" name="username"></p>
		<br>

		<h3>8. PARENT/GUARDIAN AUTHORIZATION </h3>
		<p><b>8.1</b> I fully understand that my minor’s participation in hiking and other activities provided by the Company, by nature, involves a risk of serious injury or death, including economic losses, which may result not only from my child's actions, inactions, or negligence, but also from the actions, inactions, or negligence of others, the condition of the Company’s facilities, equipment, or areas where the Activities are being conducted, the rules of the Activities, or this type of service. I acknowledge that I fully realize the dangers of participating in such Activities, and fully assume all risks associated with my child’s participation in such Activities, including, without limitation, any and all risks of bodily injury to my child, including, but not limited to, medical or hospital bills, permanent or partial disability, death, and damages to my property, caused by or arising from my child's participation in the Activities.</p>
		<p hidden>Initials:<input style="outline: none; border: 0; border-bottom: 1px solid; width: 100px; display: inline-block;" type="hidden" name="username"></p>
		<br>

		<p>THIS AGREEMENT SHALL REMAIN IN EFFECT FOR THE DURATION OF MY PARTICIPATION IN THE ACTIVITIES, DURING THIS INITIAL AND ALL SUBSEQUENT ACTIVITIES OF PARTICIPATION.</p>
		<p>I HEREBY ACKNOWLEDGE THAT I HAVE CAREFULLY READ THIS AGREEMEENT AND FULLY UNDERSTAND ALL THE TERMS, UNDERSTAND THAT I AM VOLUNTARILY GIVING UP SUBSTANTIAL LEGAL RIGHTS BY SIGNING BELOW, AND HAVE SIGNED THIS AGREEMENT FREELY AND VOLUNTARILY AND WITHOUT INDUCEMENT, ASSURANCE, OR GUARANTEE OF ANY NATURE BEING MADE TO ME. I HEREBY WARRANT THE TRUTH OF THE ABOVE STATEMENTS AND I DECLARE THAT I HAVE NOT WITHHELD ANY INFORMATION THAT WOULD INFLUENCE THE DECISION OF THE COMPANY IN ALLOWING ME TO PARTICIPATE IN THE ACTIVITIES.</p>
		<p><strong>BY SIGNING, I ACKNOWLEDGE THAT I HAVE READ AND UNDERSTOOD ALL OF THE TERMS OF THIS AGREEMENT AND THAT I AM VOLUNTARILY GIVING UP SUBSTANTIAL LEGAL RIGHTS, INCLUDING THE RIGHT TO SUE THE COMPANY.</strong></p>
		<?php } ?>
	</div>

	<address>
		<div><b>{{ $bookingInfo->tour_operator }}</b></div>
		<div>{{ $bookingInfo->address }}</span></div>
		<div>{{ $bookingInfo->city . ' - ' . $bookingInfo->postal_code . ', ' . $bookingInfo->state . ', ' . $bookingInfo->country  }}</div>
		<div>{{ $bookingInfo->phone_number }}</div>
		<div>{{ $bookingInfo->email }}</div>
	</address>

	<p><b>Date:</b> {{ date('F j, Y') }}</p>
</section>

<hr>

<section>
	<h5 class="mb-3"><b>Details of the people for whom this liability waiver is being signed.</b></h5>

	<form method="POST" action="{{ route('customer.liability-waiver-submission', [ 'bookingId' => $encBookingId ]) }}" onsubmit="return submitForm(this)" data-on-success-msg="Thank you for signing the waiver form. Have a Wonderful Day!" data-redirect-on-success="{{ $bookingInfo->website}}">
	@csrf
	<table class="table table-bordered w-auto mb-3">
			<thead><tr>
				<th class="text-center">#</th>
				<th>Name</th>
				<th>Age</th>
			</tr></thead>
			<tbody>
	@foreach($rateGroups as $rg)
<tr><th colspan="3">{{ $rg->rate_for }}</th></tr>
		@php
		$touristsCount = ($rg->infants > 0) ? $rg->infants : $rg->tourists;
		@endphp	
		@for($a = 0; $a < $touristsCount; $a++)
<tr class="align-middle">
	<td class="text-center">{{ $a + 1 }}</td>
	<td><input name="name[]" class="form-control txt-name" required></td>
	<td>
		<input type="number" name="age[]" class="form-control txt-age" min="{{ $rg->age_from }}" max="{{ $rg->age_to == 0 ? 150 : $rg->age_to }}" required>
	</td>
</tr>
		@endfor
	@endforeach


			</tbody>
		</table>

		<div>
			<label>
				<input type="checkbox" name="agree" required>
				<span>I/we agree to the conditions as mentioned in this liability waiver.</span>
			</label>
		</div>

		<hr>

		<div class="mb-3"><button class="btn btn-main btn-main-sm">Submit</button></div>
	</form>
</section>
@endsection

@section('scripts')
<script>
var bookingInfoPageUrl = "https://www.antelopelowercanyon.com/blog/";
var newOrEdit = '{{ $newOrEdit }}';

	@if($newOrEdit == 'Edit')
var liabWaivSign = {!! $bookingInfo->liability_waiver_signing !!};

$(function() {
	fillExistingData();
});
	@endif
</script>

<script src="{{ asset('js/liability-waiver.js') }}"></script>
@endsection
