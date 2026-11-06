<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * The legal documents (items 64–65), shown on the website by
 * LegalController and linked from the login page. There are two sets:
 *
 *  resqpulse   ResQPulse — the residents' app and this website, including
 *              the MDRRMO web panel: /privacy-policy, /terms,
 *              /cookie-policy, /accessibility. The residents' app has the
 *              same text (its lib/legal_text.dart).
 *  resqponder  Resqponder — the response teams' app: /resqponder/... The
 *              Resqponder app has the same text (its lib/legal_text.dart).
 *
 * When a text changes, change it in the app too, and change its version.
 * The residents' app sends the ResQPulse version a resident agreed to when
 * signing up; it's saved on their account (citizens.privacy_consent_version).
 *
 * Blocks: a plain string is a paragraph, "• " starts a bullet, and
 * **text** is bold.
 */
final class Legal
{
    /** Version of the ResQPulse documents — the one residents agree to. */
    public const VERSION = '2026-11-06';

    /** When the ResQPulse documents last changed. */
    public const UPDATED = 'November 6, 2026';

    /**
     * Both sets: name, version, date, and the documents by their web
     * address (e.g. "privacy-policy", "resqponder/terms").
     */
    public static function sets(): array
    {
        return [
            'resqpulse' => [
                'name' => 'ResQPulse',
                'version' => '2026-11-06',
                'updated' => 'November 6, 2026',
                'documents' => [
                    'privacy-policy' => [
                        'id' => 'privacy',
                        'title' => 'Privacy Policy',
                        'summary' => 'What we collect, why, who sees it and your rights',
                        'intro' => [
                            'This Privacy Policy explains what personal information ResQPulse '
                            . 'collects, why, who sees it, how long it is kept, and your rights '
                            . 'under the Data Privacy Act of 2012 (Republic Act No. 10173).',
                            'It covers the ResQPulse app and the resqpulse.com website, '
                            . 'including the MDRRMO web panel. Response teams have their own '
                            . 'Privacy Policy in the Resqponder app and at '
                            . 'resqpulse.com/resqponder/privacy-policy.',
                        ],
                        'sections' => [
                            ['Who we are', [
                                'ResQPulse is run by the Municipal Disaster Risk Reduction and '
                                . 'Management Office (MDRRMO) of the Municipality of Rosales, '
                                . 'Pangasinan. MDRRMO Rosales decides how your information is used '
                                . '(it is the "personal information controller"). In this policy, '
                                . '"MDRRMO", "we" and "us" mean MDRRMO Rosales.',
                                '**Questions or requests:** Call MDRRMO Rosales at #2441, or visit '
                                . 'the MDRRMO office, and ask for the Data Protection Officer of the '
                                . 'Municipality of Rosales.',
                            ]],
                            ['What we collect from you', [
                                '• **Your account:** your first, middle and last name, suffix, '
                                . 'mobile number, email (if you use one), password, and your address '
                                . 'in Rosales (barangay, street and zone). Your password is stored '
                                . 'scrambled (hashed), so nobody can read it, not even MDRRMO.',
                                '• **Proof of residency:** a photo of a valid ID. The law treats '
                                . 'government-issued IDs as sensitive personal information. We use '
                                . 'it only to confirm that you live in Rosales.',
                                '• **Your agreement:** when you agreed to these documents, and to '
                                . 'which version.',
                                '• **SOS alerts and reports:** the kind of emergency, what you '
                                . 'write, the photos you take, your location (GPS coordinates and '
                                . 'how accurate they are), the barangay and nearest landmark worked '
                                . 'out from it, and the time.',
                                '• **Guests:** if you send an SOS without an account, the name and '
                                . 'mobile number you enter, with the details above.',
                                '• **Your phone:** a notification token, so we can send you '
                                . 'alerts, and the internet (IP) address that reaches our server.',
                                '• **At evacuation centers:** MSWD teams may record your name, '
                                . 'age, sex, barangay and contact number with the Resqponder app. '
                                . 'Its Privacy Policy explains how.',
                            ]],
                            ['What we collect from MDRRMO staff', [
                                'For staff who use the web panel: their name, email, and a record '
                                . 'of what each person does in the panel (the Audit Log), with the '
                                . 'date and internet address.',
                            ]],
                            ['How your location is used', [
                                '• ResQPulse sends your location to MDRRMO only when you send an '
                                . 'SOS or a report.',
                                '• On Evacuation Centers, your location is used on your phone to '
                                . 'show the nearest centers. It is not sent to us.',
                                '• To show the address of a report, your phone looks up the place '
                                . 'at your location with OpenStreetMap\'s search service (Nominatim).',
                            ]],
                            ['Why we use your information', [
                                '• To receive, check and respond to SOS alerts and reports, and to '
                                . 'send the right responders.',
                                '• To confirm that an account belongs to a resident of Rosales, '
                                . 'and to keep accounts secure (codes and limits on wrong passwords).',
                                '• To send disaster alerts, and updates about your reports.',
                                '• To prevent and deal with misuse, such as false reports.',
                                '• For disaster planning and statistics, using totals that don\'t '
                                . 'identify anyone.',
                                '• To keep the records the law requires.',
                            ]],
                            ['Our legal basis', [
                                'Under Sections 12 and 13 of the Data Privacy Act, we use your '
                                . 'information because:',
                                '• you agreed to it when you created your account, including '
                                . 'specifically for your ID photo (Sections 12(a) and 13(a));',
                                '• it is needed to protect someone\'s life and health in an '
                                . 'emergency (Sections 12(d) and 13(c));',
                                '• it is needed to respond to emergencies, keep public order and '
                                . 'safety, and carry out MDRRMO\'s duties under the Philippine '
                                . 'Disaster Risk Reduction and Management Act of 2010 (Republic Act '
                                . 'No. 10121) (Sections 12(e) and 13(b));',
                                '• the law requires it, for example to keep public records '
                                . '(Section 12(c)).',
                            ]],
                            ['The AI photo check', [
                                'When an SOS or a report includes a photo, our server may send the '
                                . 'photo to Google\'s Gemini AI service to suggest what kind of '
                                . 'emergency it shows. The suggestion is only a hint for MDRRMO '
                                . 'staff and responders. A person always checks the report and '
                                . 'decides what to do; the AI does not decide whether help is sent.',
                            ]],
                            ['Who sees your information', [
                                '• **MDRRMO Rosales staff** who handle reports and accounts.',
                                '• **The response teams sent to help** (for example police, fire, '
                                . 'rescue, health and social welfare teams), in the Resqponder app. '
                                . 'They see what they need to respond: your name, mobile number, '
                                . 'location, the report and its photos. They must keep it '
                                . 'confidential.',
                                '• **Service providers** that run parts of ResQPulse for us, only '
                                . 'for that purpose: Hostinger (our server and database); Google '
                                . '(Firebase Cloud Messaging for notifications, Gmail for emails '
                                . 'with codes, Google Maps Platform for finding barangays and '
                                . 'landmarks, and Gemini for the AI photo check); Semaphore and '
                                . 'TextBee (text messages); and OpenStreetMap (maps and place '
                                . 'search).',
                                '• **Government authorities**, when the law requires it, for '
                                . 'example under a court order.',
                                'Some of these providers keep information outside the Philippines. '
                                . 'We remain responsible for it under Section 21 of the Data Privacy '
                                . 'Act. We don\'t sell your information, and we don\'t use it for '
                                . 'advertising.',
                            ]],
                            ['How long we keep it', [
                                '• **Your account** is kept while it is in use. If MDRRMO rejects '
                                . 'a sign-up, the account and its ID photo are removed.',
                                '• **SOS alerts, reports and the Audit Log** are government '
                                . 'records. We keep them while they are needed for the response, '
                                . 'follow-up and planning, and as long as records rules require '
                                . '(Republic Act No. 9470, the National Archives of the Philippines '
                                . 'Act of 2007). Then we delete them or remove what identifies you.',
                                '• **Codes** sent by text or email stop working after 10 minutes.',
                            ]],
                            ['How we protect it', [
                                '• Information travels over encrypted connections (HTTPS).',
                                '• Passwords are stored hashed, so nobody can read them.',
                                '• Only authorized staff and teams can see personal information, '
                                . 'and what staff do in the web panel is recorded.',
                                '• Codes and limits on wrong passwords protect accounts. Logging '
                                . 'out removes your account from the phone.',
                                '• If a data breach affects your information, we will tell the '
                                . 'National Privacy Commission and you, as the law requires.',
                            ]],
                            ['Your rights', [
                                'Under the Data Privacy Act, you can:',
                                '• be told how your information is used;',
                                '• get a copy of your information, including in an electronic '
                                . 'format you can reuse;',
                                '• have wrong or incomplete information corrected (you can change '
                                . 'many details yourself in Profile);',
                                '• object to the use of your information, or ask us to stop, block '
                                . 'or delete it, where the law allows;',
                                '• withdraw your consent at any time. This doesn\'t undo what was '
                                . 'done before, and records the law requires us to keep stay;',
                                '• be paid damages if you are harmed by inaccurate or unlawfully '
                                . 'used information;',
                                '• complain to the National Privacy Commission (privacy.gov.ph).',
                                'To use these rights: Call MDRRMO Rosales at #2441, or visit the '
                                . 'MDRRMO office, and ask for the Data Protection Officer of the '
                                . 'Municipality of Rosales. We may ask you to prove who you are '
                                . 'first.',
                            ]],
                            ['Children', [
                                'You must be 18 or older to create an account, or have a parent or '
                                . 'guardian agree to these documents with you. Anyone, of any age, '
                                . 'can send an SOS in an emergency.',
                            ]],
                            ['Changes to this policy', [
                                'We will post changes in the app and at '
                                . 'resqpulse.com/privacy-policy, with the date at the top. If a '
                                . 'change is important, we will ask you to agree again.',
                            ]],
                        ],
                    ],
                    'terms' => [
                        'id' => 'terms',
                        'title' => 'Terms and Conditions',
                        'summary' => 'The rules for using ResQPulse',
                        'intro' => [
                            'These Terms and Conditions are the rules for using the ResQPulse '
                            . 'app and the resqpulse.com website, including the MDRRMO web '
                            . 'panel, which are run by MDRRMO Rosales. By creating an account or '
                            . 'using them, you agree to these terms and to the Privacy Policy. '
                            . 'Response teams follow the Resqponder app\'s own terms.',
                            'If you don\'t agree, don\'t use the app. In an emergency, you can '
                            . 'always call MDRRMO Rosales at #2441 or 911.',
                        ],
                        'sections' => [
                            ['ResQPulse doesn\'t replace calling for help', [
                                '• ResQPulse helps you reach MDRRMO, but it depends on your phone, '
                                . 'your internet connection, GPS and our servers, which can fail or '
                                . 'be slow.',
                                '• We can\'t promise how fast help will arrive, or that every alert '
                                . 'or report will get through. If a life is in danger, also call '
                                . 'MDRRMO Rosales (#2441) or 911.',
                                '• Alerts can arrive late or not at all, for example when your '
                                . 'phone is off or offline, or notifications are turned off. Listen '
                                . 'to official announcements too.',
                            ]],
                            ['Your account', [
                                '• Give true and complete information, including a valid ID that '
                                . 'is really yours. MDRRMO may decline an account it can\'t verify.',
                                '• Use one account per person.',
                                '• Keep your password and codes private. You are responsible for '
                                . 'what is done with your account. Tell MDRRMO if you think someone '
                                . 'else is using it.',
                                '• You must be 18 or older, or have a parent or guardian agree to '
                                . 'these terms with you.',
                            ]],
                            ['Use ResQPulse responsibly', [
                                'Don\'t:',
                                '• send false, prank or test SOS alerts or reports;',
                                '• send photos that aren\'t of the emergency, or that are obscene '
                                . 'or abusive;',
                                '• share other people\'s personal details unless they are needed to '
                                . 'get help;',
                                '• pretend to be someone else, or use another person\'s account;',
                                '• harass MDRRMO staff or responders;',
                                '• try to break into, overload, copy or disrupt the app, the '
                                . 'website or the server.',
                                'MDRRMO may block an account that breaks these rules, and may '
                                . 'report serious misuse, such as false emergency reports, to the '
                                . 'authorities under the laws and ordinances that apply.',
                            ]],
                            ['What you send us', [
                                'You keep your rights to the photos and words you send. You allow '
                                . 'MDRRMO Rosales to use them to respond to emergencies, to keep '
                                . 'records, and for training and planning. When we use them outside '
                                . 'MDRRMO, we remove what identifies people. Only send what you have '
                                . 'the right to share.',
                            ]],
                            ['For MDRRMO staff', [
                                '• Only authorized MDRRMO Rosales staff may use the web panel.',
                                '• Residents\' information is confidential. Use it only for your '
                                . 'work, don\'t copy or share it outside it, and never post it '
                                . 'online. Misusing it can be a crime under the Data Privacy Act.',
                                '• What you do in the panel is recorded.',
                            ]],
                            ['Information in the app', [
                                '• Safety tips and first-aid steps are general guidance, not '
                                . 'medical advice. In a medical emergency, call for help.',
                                '• Hotlines, evacuation centers and maps are kept as up to date as '
                                . 'we can, but they can change. Distances are estimates.',
                                '• The AI photo check can be wrong. MDRRMO staff decide what '
                                . 'happens with a report.',
                            ]],
                            ['Changes and availability', [
                                '• The app is free. We may change, pause or stop it, for example '
                                . 'for maintenance.',
                                '• We may update these terms. We will show the date at the top, '
                                . 'and ask you to agree again if a change is important.',
                            ]],
                            ['Responsibility', [
                                'We work to keep ResQPulse accurate and running, but we provide it '
                                . 'as it is. As far as Philippine law allows, MDRRMO Rosales is not '
                                . 'responsible for losses caused by delays, outages, wrong '
                                . 'information from users, or problems with your phone or internet '
                                . 'connection. Nothing in these terms takes away rights the law '
                                . 'gives you.',
                            ]],
                            ['Law that applies', [
                                'These terms are governed by the laws of the Philippines.',
                            ]],
                            ['Contact', [
                                'Questions about these terms: call MDRRMO Rosales at #2441, or '
                                . 'visit the MDRRMO office.',
                            ]],
                        ],
                    ],
                    'cookie-policy' => [
                        'id' => 'cookies',
                        'title' => 'Cookie Policy',
                        'summary' => 'What the app and the website store on your device',
                        'intro' => [
                            'This Cookie Policy explains the small files and data ResQPulse '
                            . 'stores on your phone or browser. We don\'t use cookies or similar '
                            . 'tools for advertising, or to follow you across other websites or '
                            . 'apps.',
                        ],
                        'sections' => [
                            ['On the MDRRMO web panel (resqpulse.com)', [
                                'The web panel only uses what it needs to work, so it doesn\'t ask '
                                . 'for cookie consent:',
                                '• **Session cookie:** keeps you signed in while you use the '
                                . 'panel. It ends when you sign out or your session expires.',
                                '• **XSRF-TOKEN:** protects forms from being sent by other '
                                . 'websites.',
                                '• **"Remember me" cookie:** only if you tick Remember me. It '
                                . 'keeps you signed in on that browser until you sign out.',
                                '• **Browser storage** (localStorage and sessionStorage): small '
                                . 'settings, such as which SOS alerts you have already seen and '
                                . 'which tab you were on. They don\'t hold personal information.',
                                'Some pages load maps from Google Maps, and fonts and code from '
                                . 'Google Fonts and jsDelivr. These providers receive your internet '
                                . 'address and may use their own cookies under their own policies. '
                                . 'The legal pages, like this one, load nothing from other websites.',
                            ]],
                            ['In the ResQPulse app', [
                                'The app doesn\'t use browser cookies. It keeps on your phone:',
                                '• your login and basic account details, so you stay logged in;',
                                '• for guests, the name and mobile number entered for SOS, so you '
                                . 'don\'t have to type them again;',
                                '• your alerts and settings, such as cleared alerts;',
                                '• a notification token from Firebase Cloud Messaging, so the '
                                . 'phone can get alerts.',
                                'Map pictures from OpenStreetMap are only kept while the app is '
                                . 'open. Logging out removes your login and notification token from '
                                . 'the phone.',
                            ]],
                            ['Your choices', [
                                '• **Website:** you can block or delete cookies in your browser\'s '
                                . 'settings, but you can\'t sign in to the panel without the session '
                                . 'cookie.',
                                '• **App:** log out, or clear the app\'s storage in your phone\'s '
                                . 'settings. Uninstalling the app removes everything it saved.',
                            ]],
                            ['Contact', [
                                'Questions: call MDRRMO Rosales at #2441, or visit the MDRRMO '
                                . 'office.',
                            ]],
                        ],
                    ],
                    'accessibility' => [
                        'id' => 'accessibility',
                        'title' => 'Accessibility',
                        'summary' => 'How we make ResQPulse usable for everyone',
                        'intro' => [
                            'MDRRMO Rosales wants everyone, including persons with '
                            . 'disabilities and older residents, to be able to use ResQPulse and '
                            . 'to get help.',
                        ],
                        'sections' => [
                            ['What we do', [
                                '• Text in the app follows your phone\'s font size setting.',
                                '• We check new and updated screens for buttons that are large '
                                . 'enough to tap, strong color contrast, and labels that screen '
                                . 'readers such as TalkBack can read.',
                                '• Information isn\'t shown by color alone; there are words or '
                                . 'icons too.',
                                '• Documents like this one use headings that screen readers can '
                                . 'jump between.',
                                '• For the website, we aim to meet the Web Content Accessibility '
                                . 'Guidelines (WCAG) 2.1 at level AA. The Philippines adopted WCAG '
                                . 'for government websites in the Philippine Web Accessibility '
                                . 'Policy (DICT).',
                            ]],
                            ['What may still be hard to use', [
                                '• Maps and taking a photo of your ID can be hard to use with a '
                                . 'screen reader.',
                                '• SOS works best with location turned on.',
                                '• Some older screens haven\'t been fully checked yet. We are '
                                . 'working through them.',
                                'If any part of ResQPulse doesn\'t work for you, you never need the '
                                . 'app to get help: call MDRRMO Rosales at #2441 or 911.',
                            ]],
                            ['Tell us about a problem', [
                                'If something is hard to use, call MDRRMO Rosales at #2441, or '
                                . 'visit the MDRRMO office. Tell us which screen it was and what '
                                . 'happened. We will help you another way and work on fixing it.',
                                'We are guided by the Magna Carta for Persons with Disability '
                                . '(Republic Act No. 7277, as amended) and the Philippine Web '
                                . 'Accessibility Policy.',
                            ]],
                        ],
                    ],
                ],
            ],
            'resqponder' => [
                'name' => 'Resqponder',
                'version' => '2026-11-06',
                'updated' => 'November 6, 2026',
                'documents' => [
                    'resqponder/privacy-policy' => [
                        'id' => 'privacy',
                        'title' => 'Privacy Policy',
                        'summary' => 'Your team\'s information, and the information you handle',
                        'intro' => [
                            'This Privacy Policy explains how Resqponder, the ResQPulse app '
                            . 'for MDRRMO Rosales response teams, handles personal information: '
                            . 'your team\'s, and that of the residents, casualties and evacuees '
                            . 'you work with. It follows the Data Privacy Act of 2012 (Republic '
                            . 'Act No. 10173).',
                            'Residents have their own Privacy Policy in the ResQPulse app and '
                            . 'at resqpulse.com/privacy-policy.',
                        ],
                        'sections' => [
                            ['Who we are', [
                                'Resqponder is run by the Municipal Disaster Risk Reduction and '
                                . 'Management Office (MDRRMO) of the Municipality of Rosales, '
                                . 'Pangasinan, which decides how the information is used (the '
                                . '"personal information controller"). Agency teams use Resqponder '
                                . 'for MDRRMO, so they must follow its rules when they handle '
                                . 'residents\' information.',
                                '**Questions or requests:** Call MDRRMO Rosales at #2441, or visit '
                                . 'the MDRRMO office, and ask for the Data Protection Officer of the '
                                . 'Municipality of Rosales.',
                            ]],
                            ['Your team\'s information', [
                                '• **The agency account:** the account name, agency, station or '
                                . 'unit, badge number, email and mobile number. MDRRMO sets it up; '
                                . 'there is one account per agency, shared by its team, and its '
                                . 'password is stored hashed.',
                                '• **What the team does:** incidents it accepts, joins as backup '
                                . 'or declines, backup requests, incident reports, and evacuation '
                                . 'centers and evacuees it adds, each with the time.',
                                '• **Logins:** when the account logs in and out, with the internet '
                                . '(IP) address, in MDRRMO\'s Audit Log.',
                                '• **The phone:** a notification token, so incidents can ring the '
                                . 'phone.',
                            ]],
                            ['Information you see and record about others', [
                                '• **SOS alerts and reports:** the name and mobile number of the '
                                . 'person who reported, where they are (address, landmark and GPS '
                                . 'pin), what they wrote, their photos, and MDRRMO\'s instructions.',
                                '• **Incident reports:** what the team found, effects, actions '
                                . 'taken, a photo, and for each person dead, injured or missing: '
                                . 'name, age, sex, address, cause and remarks. The law treats age '
                                . 'and health information as sensitive personal information.',
                                '• **Evacuees (MSWD):** the name, age, sex, barangay and contact '
                                . 'number of people at evacuation centers.',
                            ]],
                            ['How location is used', [
                                '• Your phone\'s location is used on the phone, to show where you '
                                . 'are on the maps and to draw routes. It isn\'t sent to MDRRMO, and '
                                . 'your team isn\'t tracked.',
                                '• To draw a route, the phone sends the start and end points to '
                                . 'the OSRM route service.',
                                '• Searching for a place when adding an evacuation center uses '
                                . 'OpenStreetMap\'s search service (Nominatim). The center\'s location '
                                . 'is saved, and MDRRMO and residents see it.',
                            ]],
                            ['Why it is used', [
                                '• To send incidents to the right teams, and to coordinate the '
                                . 'response and backup.',
                                '• To keep the MDRRMC incident reports, and evacuation and relief '
                                . 'records.',
                                '• To keep the accounts secure, and to know which team did what.',
                                '• For disaster planning and statistics, using totals that don\'t '
                                . 'identify anyone.',
                            ]],
                            ['Our legal basis', [
                                'Under Sections 12 and 13 of the Data Privacy Act, the information '
                                . 'is used because:',
                                '• it is needed to respond to emergencies, keep public order and '
                                . 'safety, and carry out MDRRMO\'s duties under the Philippine '
                                . 'Disaster Risk Reduction and Management Act of 2010 (Republic Act '
                                . 'No. 10121) (Sections 12(e) and 13(b));',
                                '• it is needed to protect people\'s life and health (Sections '
                                . '12(d) and 13(c));',
                                '• the law requires records to be kept (Section 12(c)).',
                            ]],
                            ['The AI photo check', [
                                'Photos residents send may be checked by Google\'s Gemini AI '
                                . 'service, which suggests what kind of emergency they show. '
                                . 'Resqponder shows it as the "AI guess" and the "AI photo check". '
                                . 'It is only a hint and can be wrong: check the photo and the place '
                                . 'yourself.',
                            ]],
                            ['Who sees it', [
                                '• **MDRRMO Rosales staff** see everything, in the web panel.',
                                '• **Other teams on the same incident** see your account\'s name, '
                                . 'agency, badge number, station and mobile number, and the team\'s '
                                . 'report.',
                                '• **MSWD teams** see the list of evacuees.',
                                '• **Service providers**, only to run Resqponder: Hostinger (our '
                                . 'server and database); Google (Firebase Cloud Messaging for the '
                                . 'ring, and Gemini for the AI photo check); OpenStreetMap (maps and '
                                . 'place search); and the OSRM route service.',
                                '• **Government authorities**, when the law requires it.',
                                'Some of these providers keep information outside the Philippines. '
                                . 'MDRRMO remains responsible for it under Section 21 of the Data '
                                . 'Privacy Act. Nothing is sold or used for advertising.',
                            ]],
                            ['How long it is kept', [
                                '• **The agency account** is kept while the agency works with '
                                . 'MDRRMO. MDRRMO can deactivate it.',
                                '• **Incident reports, evacuation records and the Audit Log** are '
                                . 'government records, kept as long as they are needed and as '
                                . 'records rules require (Republic Act No. 9470, the National '
                                . 'Archives of the Philippines Act of 2007). Then they are deleted, '
                                . 'or what identifies people is removed.',
                                '• **The notification token** is removed when you log out, and '
                                . 'when MDRRMO resets the password or deactivates the account.',
                            ]],
                            ['How it is protected', [
                                'MDRRMO\'s part:',
                                '• Information travels over encrypted connections (HTTPS), and '
                                . 'passwords are stored hashed.',
                                '• Each team only gets the incidents MDRRMO sends it, and only '
                                . 'MSWD sees evacuees.',
                                '• A link to a report\'s PDF stops working after 30 minutes.',
                                '• What is done in the system is recorded.',
                                'Your part:',
                                '• Lock the phone with a PIN, and don\'t leave it unattended while '
                                . 'logged in.',
                                '• If a phone is lost, or someone leaves the team, ask MDRRMO to '
                                . 'reset the password: that signs out every phone.',
                                '• Don\'t screenshot, forward or post residents\' information. "Copy '
                                . 'for a message" leaves out the reporter\'s name and number; share '
                                . 'it only with people working on the response.',
                            ]],
                            ['Rights', [
                                '• Team members can ask MDRRMO to see or correct the agency '
                                . 'account\'s details.',
                                '• Residents, casualties\' families and evacuees have rights under '
                                . 'the Data Privacy Act, including to be told how their information '
                                . 'is used, to get a copy, and to have it corrected. If someone asks '
                                . 'you, refer them to MDRRMO Rosales.',
                                '• Anyone can complain to the National Privacy Commission '
                                . '(privacy.gov.ph).',
                                'Requests: Call MDRRMO Rosales at #2441, or visit the MDRRMO '
                                . 'office, and ask for the Data Protection Officer of the '
                                . 'Municipality of Rosales.',
                            ]],
                            ['Changes to this policy', [
                                'Changes will be posted in the app and at '
                                . 'resqpulse.com/resqponder/privacy-policy, with the date at the top.',
                            ]],
                        ],
                    ],
                    'resqponder/terms' => [
                        'id' => 'terms',
                        'title' => 'Terms and Conditions',
                        'summary' => 'The rules for using Resqponder',
                        'intro' => [
                            'These Terms and Conditions are the rules for using Resqponder, '
                            . 'the ResQPulse app for response teams, which is run by MDRRMO '
                            . 'Rosales. By logging in, you agree to them and to Resqponder\'s '
                            . 'Privacy Policy.',
                        ],
                        'sections' => [
                            ['Who may use Resqponder', [
                                'Only authorized personnel of MDRRMO Rosales and its partner '
                                . 'agencies, using the agency account MDRRMO set up for them.',
                            ]],
                            ['The agency account', [
                                '• It is shared by your agency\'s team. Keep the password within '
                                . 'the agency and never share it outside.',
                                '• Ask MDRRMO to reset the password when someone leaves the team '
                                . 'or a phone is lost. A reset signs out every phone.',
                                '• Log out of a phone the team doesn\'t control.',
                                '• What is done with the account is recorded, with the time.',
                            ]],
                            ['Residents\' information is confidential', [
                                '• Use names, numbers, locations, photos and reports only for the '
                                . 'response.',
                                '• Don\'t screenshot, copy, forward or post them, including in '
                                . 'group chats outside the response. Never post them online.',
                                '• Don\'t contact residents for anything unrelated to the response.',
                                'Misusing personal information can be a crime under the Data '
                                . 'Privacy Act, and can also have consequences under your agency\'s '
                                . 'rules.',
                            ]],
                            ['Responding to incidents', [
                                '• Accept an incident only if your team can respond. If it can\'t, '
                                . 'decline it quickly, so MDRRMO can send another team.',
                                '• Request backup when you need it. MDRRMO decides which teams to '
                                . 'send.',
                                '• Follow your agency\'s protocols and MDRRMO\'s instructions.',
                            ]],
                            ['Incident reports', [
                                '• Reports must be true and complete. They are official MDRRMC '
                                . 'records.',
                                '• Record casualties\' details only as needed for the report.',
                                '• MDRRMO may correct a report before approving it, and says what '
                                . 'it changed.',
                            ]],
                            ['Evacuees (MSWD)', [
                                '• Tell each person what is recorded and why before saving; the '
                                . 'Log Evacuee screen says what to tell them.',
                                '• Record only what the form asks. For a child, record the details '
                                . 'with a parent or guardian present.',
                            ]],
                            ['Resqponder is a tool, not the only channel', [
                                '• It depends on the phone, the internet connection, GPS and the '
                                . 'server. Incidents can ring late, or not at all, when the phone is '
                                . 'offline.',
                                '• The ring plays on the phone\'s alarm volume. Keep it up, and '
                                . 'keep your radio on too.',
                                '• Maps, routes, distances and times are estimates. Roads can be '
                                . 'closed or flooded; trust what you see.',
                                '• The AI guess can be wrong. Safety tips are general guidance; '
                                . 'follow your training.',
                            ]],
                            ['Changes and access', [
                                'MDRRMO may change, pause or stop Resqponder, and may deactivate '
                                . 'an account; that signs out its phones. These terms may be '
                                . 'updated, with the date at the top.',
                            ]],
                            ['Responsibility', [
                                'MDRRMO works to keep Resqponder accurate and running, but '
                                . 'provides it as it is. As far as Philippine law allows, MDRRMO '
                                . 'Rosales is not responsible for losses caused by delays, outages, '
                                . 'or problems with the phone or the internet connection.',
                            ]],
                            ['Law that applies', [
                                'These terms are governed by the laws of the Philippines.',
                            ]],
                            ['Contact', [
                                'Questions: call MDRRMO Rosales at #2441, or visit the MDRRMO '
                                . 'office.',
                            ]],
                        ],
                    ],
                    'resqponder/cookie-policy' => [
                        'id' => 'cookies',
                        'title' => 'Cookie Policy',
                        'summary' => 'What Resqponder keeps on the phone',
                        'intro' => [
                            'Resqponder doesn\'t use browser cookies, and nothing in it is used '
                            . 'for advertising or to follow you. This explains what it keeps on '
                            . 'the phone.',
                        ],
                        'sections' => [
                            ['What Resqponder keeps on the phone', [
                                '• **The login:** the agency account\'s login and its details '
                                . '(name, agency, station, badge number, email and mobile number), '
                                . 'so the team stays logged in.',
                                '• **A notification token** from Firebase Cloud Messaging, so '
                                . 'incidents can ring the phone.',
                                '• **Cleared alerts:** which disaster alerts you cleared.',
                                '• **Photos for a report:** the photo you take or choose stays in '
                                . 'the app\'s temporary storage until the phone clears it.',
                                'Map pictures from OpenStreetMap are only kept while the app is '
                                . 'open.',
                            ]],
                            ['Outside the app', [
                                '• A report\'s PDF opens in the phone\'s browser or PDF viewer, '
                                . 'through a link that works for 30 minutes. The browser may keep '
                                . 'the file in its downloads or history.',
                                '• "Copy for a message" puts the incident\'s details on the phone\'s '
                                . 'clipboard, without the reporter\'s name and number.',
                            ]],
                            ['Your choices', [
                                '• Logging out removes the login from the phone, and the '
                                . 'notification token from MDRRMO\'s list.',
                                '• You can clear the app\'s storage in the phone\'s settings. '
                                . 'Uninstalling the app removes everything it saved.',
                            ]],
                            ['Contact', [
                                'Questions: call MDRRMO Rosales at #2441, or visit the MDRRMO '
                                . 'office.',
                            ]],
                        ],
                    ],
                    'resqponder/accessibility' => [
                        'id' => 'accessibility',
                        'title' => 'Accessibility',
                        'summary' => 'How we make Resqponder usable for every responder',
                        'intro' => [
                            'MDRRMO Rosales wants every responder to be able to use '
                            . 'Resqponder, including people with low vision or hearing loss.',
                        ],
                        'sections' => [
                            ['What we do', [
                                '• A new incident rings like a call: an alarm on the phone\'s alarm '
                                . 'volume and a full-screen alert. If the sound can\'t play, the '
                                . 'phone vibrates instead.',
                                '• Text follows the phone\'s font size setting.',
                                '• We check new and updated screens for buttons that are large '
                                . 'enough to tap, strong color contrast, and labels that screen '
                                . 'readers such as TalkBack can read.',
                                '• An incident\'s status and priority are written out, not shown by '
                                . 'color alone.',
                                '• Documents like this one use headings that screen readers can '
                                . 'jump between.',
                            ]],
                            ['What may still be hard to use', [
                                '• If the alarm volume is down, the ring is silent. If you can\'t '
                                . 'hear it, keep the phone where you can see the full-screen alert.',
                                '• Maps and routes are hard to use with a screen reader. The '
                                . 'address, landmark and Call buttons work without the map.',
                                '• Some older screens haven\'t been fully checked yet. We are '
                                . 'working through them.',
                            ]],
                            ['Tell us about a problem', [
                                'If something is hard to use, call MDRRMO Rosales at #2441. Tell '
                                . 'us which screen it was and what happened.',
                                'We are guided by the Magna Carta for Persons with Disability '
                                . '(Republic Act No. 7277, as amended).',
                            ]],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** The ResQPulse documents (or the other set's), by web address. */
    public static function documents(string $set = 'resqpulse'): array
    {
        return self::sets()[$set]['documents'] ?? [];
    }

    /**
     * One document by its web address, with the set it belongs to:
     * ['set' => 'resqponder', 'document' => [...]], or null.
     */
    public static function find(string $path): ?array
    {
        foreach (self::sets() as $key => $set) {
            if (isset($set['documents'][$path])) {
                return ['set' => $key, 'document' => $set['documents'][$path]];
            }
        }

        return null;
    }

    /**
     * A section's blocks for the page: paragraphs, and bullets grouped
     * into lists.
     *
     * @return list<array{type: 'p', text: string}|array{type: 'ul', items: list<string>}>
     */
    public static function blocks(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if (str_starts_with($block, '• ')) {
                $item = mb_substr($block, 2);
                $last = array_key_last($out);
                if ($last !== null && $out[$last]['type'] === 'ul') {
                    $out[$last]['items'][] = $item;
                } else {
                    $out[] = ['type' => 'ul', 'items' => [$item]];
                }
            } else {
                $out[] = ['type' => 'p', 'text' => $block];
            }
        }

        return $out;
    }

    /** Escapes text for HTML, with **text** as <strong>. */
    public static function inline(string $text): HtmlString
    {
        $parts = explode('**', $text);
        $html = '';
        foreach ($parts as $i => $part) {
            $html .= $i % 2 === 1 ? '<strong>' . e($part) . '</strong>' : e($part);
        }

        return new HtmlString($html);
    }
}
