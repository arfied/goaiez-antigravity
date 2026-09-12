# Related Schema Definitions

## DmButton

A single inline button rendered inside an auto-DM via Meta's button_template.
Up to 3 buttons per automation. `url` and `postback` work on Instagram and
Facebook; `phone` is Facebook-only. When buttons are set, `dmMessage` becomes
the button_template text and must be 640 characters or less.


### Properties

- **type** (required) `string`: No description - one of: url, postback, phone
- **title** (required) `string`: Button label (20 chars max) (max: 20)
- **url** `string`: Target URL (required when type is url)
- **payload** `string`: Postback payload delivered via the messaging_postbacks webhook (required when type is postback)
- **phone** `string`: Phone number, e.g. +14155551234 (required when type is phone; Facebook only)

## CommentAutomationTemplate

A Meta generic template (product card) sent as the automation's first DM.
It REPLACES the plain `dmMessage` bubble: a Meta message carries one body
shape, and a comment gets exactly one private reply, so the card and the
text cannot both be delivered. Put your selling copy in `subtitle`.
Mutually exclusive with `buttons` (sending both is a 400). Works on both
the `comment` and `story_reply` triggers.
Up to 10 elements, rendered as a horizontally swipeable carousel.
Rendering confirmed on the Instagram and Messenger mobile apps.


### Properties

- **type** (required) `string`: No description - one of: generic
- **imageAspectRatio** `string`: Facebook only. How Messenger renders each element imageUrl: horizontal (1.91:1, the default) or square (1:1). Instagram has no such setting, so an Instagram automation carrying it is a 400. - one of: horizontal, square
- **elements** (required) `array`: No description

## CommentAutomationAudience

Who a comment automation answers. Instagram only - Meta exposes the follow
relationship on no other platform, and only for people who have MESSAGED the
account (a comment grants no consent). `whenUnknown` is therefore the important
setting: it decides what happens for a first-time commenter.


### Properties

- **followerStatus** `string`: No description - one of: any, follower, non_follower (default: any)
- **minFollowerCount** `integer`: Skip commenters with fewer followers than this. Omit for no size rule. (min: 0)
- **whenUnknown** `string`: What to do when Instagram will not reveal the follow relationship.
  * `send` (default) - deliver the DM anyway (fails open).
  * `skip` - stay silent.
  * `verify` - send `followGate.message` with a confirm button. Tapping it is a
    message, which grants consent, so the re-check on the tap resolves and the
    real DM (or `followGate.notFollowingMessage`) follows automatically.
 - one of: send, skip, verify (default: send)

## CommentAutomationFollowGate

Copy for the follow gate. Sensible defaults are used for any field left empty.

### Properties

- **message** `string`: Confirmation DM sent when whenUnknown=verify. (max: 640)
- **buttonLabel** `string`: Confirm button label. Defaults to "I'm following". (max: 20)
- **notFollowingMessage** `string`: Sent to a commenter we know does not follow (followerStatus=follower). Omit to stay silent on a keyword comment; a confirm tap always gets an answer. (max: 1000)

---
