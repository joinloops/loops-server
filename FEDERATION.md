# Federation Documentation

This document outlines the ActivityPub federation implementation in Loops, including supported activities, actors, and discovery mechanisms.

## Overview

Loops implements the ActivityPub protocol to enable federation with other ActivityPub-compatible platforms. This allows users to follow, interact with, and share content across the fediverse.

## Protocol Support

- **ActivityPub**: Core protocol for federation
- **WebFinger**: User and instance discovery (RFC 7033)
- **HTTP Signatures**: Request authentication
- **Shared Inbox**: Optimized delivery mechanism

## Instance Actor

Loops implements an instance-level actor to represent the server itself in federation activities.

### Details

- **Username**: The instance domain (e.g., `loops.example.com`)
- **Actor Type**: `Application`
- **Purpose**: 
  - Server-to-server authentication
  - Instance metadata exchange
  - Delete/tombstone notifications
  - Relay subscriptions

### Endpoints

```
GET https://loops.example.com/ap/actor
```

**Response Example:**
```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "id": "https://loops.example.com/ap/actor",
  "type": "Application",
  "preferredUsername": "loops.example.com",
  "inbox": "https://loops.example.com/ap/actor/inbox",
  "outbox": "https://loops.example.com/ap/actor/outbox",
  "publicKey": {
    "id": "https://loops.example.com/ap/actor#main-key",
    "owner": "https://loops.example.com/ap/actor",
    "publicKeyPem": "-----BEGIN PUBLIC KEY-----\n..."
  },
  "manuallyApprovesFollowers": true,
  "url": "https://loops-server.test/about?instance_actor=true"
}
```

## Shared Inbox

Loops implements a shared inbox to optimize federation delivery. Instead of delivering activities to each follower's individual inbox, remote servers can deliver once to the shared inbox.

### Configuration

- **Endpoint**: `POST https://loops.example.com/ap/inbox`
- **Authentication**: HTTP Signatures required
- **Rate Limiting**: Applied per remote instance

### Benefits

- Reduces network overhead
- Improves delivery performance
- Simplifies remote server implementation

### Processing

When an activity arrives at the shared inbox:

1. Verify HTTP signature
2. Validate activity structure
3. Determine target recipients on this instance
4. Queue for processing
5. Distribute to relevant local users

## WebFinger

WebFinger enables discovery of users and their ActivityPub actor endpoints.

### Endpoint

```
GET /.well-known/webfinger?resource=acct:username@loops.example.com
```

### Response Example

```json
{
  "subject": "acct:alice@loops.example.com",
  "aliases": [
    "https://loops.example.com/users/alice",
    "https://loops.example.com/@alice"
  ],
  "links": [
    {
      "rel": "self",
      "type": "application/activity+json",
      "href": "https://loops.example.com/users/alice"
    },
    {
      "rel": "http://webfinger.net/rel/profile-page",
      "type": "text/html",
      "href": "https://loops.example.com/@alice"
    }
  ]
}
```

### Instance WebFinger

The instance actor is also discoverable via WebFinger:

```
GET /.well-known/webfinger?resource=acct:loops.example.com@loops.example.com
```

## Supported Activities

### Outbound Activities

Activities that Loops sends to other federated instances.

#### Create

Sent when a user publishes a new video post.

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Create",
  "actor": "https://loops.example.com/users/alice",
  "object": {
    "type": "Note",
    "id": "https://loops.example.com/posts/123",
    "attributedTo": "https://loops.example.com/users/alice",
    "content": "Check out this cool video!",
    "url": "https://loops.example.com/posts/123",
    "attachment": [
      {
        "type": "Document",
        "mediaType": "video/mp4",
        "url": "https://loops.example.com/storage/videos/123.mp4"
      }
    ],
    "published": "2024-01-15T12:00:00Z"
  }
}
```

#### Update

Sent when a user updates their profile or edits a post.

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Update",
  "actor": "https://loops.example.com/users/alice",
  "object": {
    "type": "Person",
    "id": "https://loops.example.com/users/alice",
    "name": "Alice Updated",
    "summary": "New bio here"
  }
}
```

#### Delete

Sent when a user deletes a post or account.

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Delete",
  "actor": "https://loops.example.com/users/alice",
  "object": {
    "id": "https://loops.example.com/posts/123",
    "type": "Tombstone"
  }
}
```

When an account is deleted (after its 30 day grace period, or right away if the user skips it), a single `Delete` with the actor as its object is sent to the shared inbox of every known server. Individual posts are not deleted one by one; receivers are expected to remove everything the actor owns.

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "id": "https://loops.example.com/ap/users/123#delete",
  "type": "Delete",
  "actor": "https://loops.example.com/ap/users/123",
  "object": "https://loops.example.com/ap/users/123",
  "to": ["https://www.w3.org/ns/activitystreams#Public"]
}
```

Afterwards the actor URL answers `410 Gone` with a `Tombstone` (`formerType: Person`), so servers that missed the `Delete` can still clean up when they refetch the actor.

#### Follow

Sent when a user follows another account.

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Follow",
  "actor": "https://loops.example.com/users/alice",
  "object": "https://mastodon.social/users/bob"
}
```

#### Accept

Sent in response to a Follow activity (for non-locked accounts).

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Accept",
  "actor": "https://loops.example.com/users/alice",
  "object": {
    "type": "Follow",
    "id": "https://mastodon.social/users/bob/follows/456",
    "actor": "https://mastodon.social/users/bob",
    "object": "https://loops.example.com/users/alice"
  }
}
```

#### Reject

Sent to deny a Follow request (for locked/private accounts).

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Reject",
  "actor": "https://loops.example.com/users/alice",
  "object": {
    "type": "Follow",
    "id": "https://mastodon.social/users/bob/follows/456",
    "actor": "https://mastodon.social/users/bob",
    "object": "https://loops.example.com/users/alice"
  }
}
```

#### Like

Sent when a user likes a post.

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Like",
  "actor": "https://loops.example.com/users/alice",
  "object": "https://mastodon.social/users/bob/statuses/789"
}
```

#### Announce

Sent when a user shares/boosts a post.

```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Announce",
  "actor": "https://loops.example.com/users/alice",
  "object": "https://mastodon.social/users/bob/statuses/789",
  "published": "2024-01-15T12:00:00Z"
}
```

#### Undo

Sent to reverse previous activities (unfollow, unlike, unshare).

**Undo Follow:**
```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Undo",
  "actor": "https://loops.example.com/users/alice",
  "object": {
    "type": "Follow",
    "id": "https://loops.example.com/activities/follow/123",
    "actor": "https://loops.example.com/users/alice",
    "object": "https://mastodon.social/users/bob"
  }
}
```

**Undo Like:**
```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Undo",
  "actor": "https://loops.example.com/users/alice",
  "object": {
    "type": "Like",
    "id": "https://loops.example.com/activities/like/456",
    "actor": "https://loops.example.com/users/alice",
    "object": "https://mastodon.social/users/bob/statuses/789"
  }
}
```

**Undo Announce:**
```json
{
  "@context": "https://www.w3.org/ns/activitystreams",
  "type": "Undo",
  "actor": "https://loops.example.com/users/alice",
  "object": {
    "type": "Announce",
    "id": "https://loops.example.com/activities/announce/789",
    "actor": "https://loops.example.com/users/alice",
    "object": "https://mastodon.social/users/bob/statuses/789"
  }
}
```

### Inbound Activities

Activities that Loops processes from other federated instances.

#### Create

Processed when a remote user publishes content. Stored in local database and displayed to followers.

**Supported Object Types:**
- `Note` (text posts)

#### Update

Processed to update remote user profiles and post content.

#### Delete

Processed to remove posts or mark accounts as deleted. Tombstones are created for deleted content.

#### Follow

Processed when a remote user wants to follow a local user. Triggers Accept/Reject response based on account privacy settings.

#### Accept

Processed when a remote user accepts a follow request from a local user.

#### Reject

Processed when a remote user rejects a follow request from a local user.

#### Like

Processed when a remote user likes a local post. Increments like counter and notifies the post author.

#### Announce

Processed when a remote user shares a local post. Increments share counter and notifies the post author.

#### Undo

Processed to reverse previous activities. Handles unfollows, unlikes, and unshares.

#### Block

Processed when a remote user blocks a local user. Removes follow relationships and prevents future interactions.

## Actor Types

Loops supports the following ActivityPub actor types:

- **Person**: Individual user accounts
- **Application**: The instance actor
- **Service**: Bot accounts or automated services
- **Group**: Planned for future group/community support

## Object Types

Supported ActivityStreams object types:

- **Note**: Primary content type for Loops posts (for optimal compatibility)
- **Tombstone**: Deleted content markers

## Coauthors (Creator Collaborations)

A Loops video can credit one coauthor besides its author. Coauthors must have an account on the author's server, and they agree to each credit, either per video or by trusting the author ahead of time.

The author stays the only `actor` and `attributedTo`, so software that does not understand coauthors sees an ordinary post. The Note gains a `coauthors` array in the `https://joinloops.org/ns#` namespace. The coauthor's actor and followers collection are added to the Note's addressing, and the post is delivered to the coauthor's followers too.

```json
{
  "@context": [
    "https://www.w3.org/ns/activitystreams",
    {
      "loops": "https://joinloops.org/ns#",
      "coauthors": { "@id": "loops:coauthors", "@container": "@set" },
      "CoauthorAuthorization": "loops:CoauthorAuthorization"
    }
  ],
  "type": "Note",
  "id": "https://loops.example/ap/users/100/video/200",
  "attributedTo": "https://loops.example/ap/users/100",
  "coauthors": [
    {
      "type": "CoauthorAuthorization",
      "id": "https://loops.example/ap/users/101/coauthor_authorizations/300",
      "attributedTo": "https://loops.example/ap/users/101"
    }
  ]
}
```

### CoauthorAuthorization

Each entry points at a public attestation, served by the coauthor's server under the coauthor's actor, stating that the coauthor agreed to be credited on that Note. The vocabulary follows the `interactingObject` / `interactionTarget` convention used by GoToSocial interaction approvals and FEP-044f `QuoteAuthorization`.

```json
{
  "@context": ["https://www.w3.org/ns/activitystreams", { "...": "same terms as above, plus gts:interactingObject / gts:interactionTarget" }],
  "type": "CoauthorAuthorization",
  "id": "https://loops.example/ap/users/101/coauthor_authorizations/300",
  "attributedTo": "https://loops.example/ap/users/101",
  "interactingObject": "https://loops.example/ap/users/100/video/200",
  "interactionTarget": "https://loops.example/ap/users/100",
  "published": "2026-10-27T10:00:00Z"
}
```

Receivers must not trust the copy embedded in the Note. Loops fetches each attestation from its `id` and accepts the credit only if all of the following hold:

- `type` is `CoauthorAuthorization` and `id` equals the URL that was fetched.
- `interactingObject` is the Note and `interactionTarget` is the Note's author.
- The attestation, the coauthor and the Note's author are all on the same host.
- `attributedTo` resolves to an actor other than the author.

Claims that fail are ignored and the post is shown without the credit.

### Withdrawing a credit

When a coauthor leaves, or the author removes them:

- The attestation URL starts returning `410 Gone` with a `Tombstone` (`formerType: CoauthorAuthorization`).
- The author sends an `Update` of the Note. On `Update`, `coauthors` is authoritative: an empty array means the credit was removed.
- The coauthor sends a `Delete` whose object is the `CoauthorAuthorization` (with `interactingObject`), signed by the coauthor.

## Security

### HTTP Signatures

Loops signs and verifies both [RFC 9421 HTTP Message Signatures](https://www.rfc-editor.org/rfc/rfc9421) and [draft-cavage-http-signatures-12](https://datatracker.ietf.org/doc/html/draft-cavage-http-signatures-12).

**Outbound (double-knocking):**
- The first attempt to an unknown server is signed with RFC 9421: `@method`, `@target-uri`, `@authority` and, when there is a body, `content-digest` ([RFC 9530](https://www.rfc-editor.org/rfc/rfc9530)), with `created`, `keyid` and `alg="rsa-v1_5-sha256"` parameters
- If the server rejects it (any 4xx other than 404, 408, 410 or 429, or a 5xx), the request is sent again signed with draft-cavage (`(request-target) host date digest`, `rsa-sha256`)
- Whichever scheme succeeds is remembered per server (RFC 9421 for 30 days, draft-cavage for 7 days so upgrades are picked up), so later deliveries need only one request
- A server that sends Loops a valid RFC 9421 signature is assumed to accept RFC 9421 too

**Inbound:**
- Requests carrying `Signature-Input` are verified as RFC 9421, everything else as draft-cavage
- RFC 9421 signatures must use `rsa-v1_5-sha256` (or no `alg` with an RSA key), cover `@method` and the target URI, and cover a valid `content-digest` when there is a body; `created` must be within 60 minutes and `expires`, if present, in the future
- draft-cavage signatures must cover `(request-target) host date`, use `rsa-sha256` or `hs2019`, and a signed `Digest` must match the body
- Key size: Minimum 2048 bits RSA

### Verification Process

1. Extract the signature from the HTTP headers (RFC 9421 or draft-cavage)
2. Fetch the actor's public key
3. Verify the signature matches the request
4. Check freshness (`created` for RFC 9421, the `Date` header for draft-cavage; reject if more than 60 minutes off)
5. Process activity if valid

### Domain Blocking

Administrators can block domains to prevent federation with specific instances. Blocked domains:

- Cannot deliver activities to this instance
- Cannot fetch user profiles or posts
- Are filtered from search results
- Have existing content hidden or removed


## Delivery

### Outbound Delivery

Activities are delivered asynchronously using Laravel queues:

1. Activity is queued for delivery
2. Recipients are determined (followers, mentions)
3. Remote inboxes are identified
4. HTTP POST requests are signed and sent
5. Failed deliveries are retried with exponential backoff
6. Permanently failed deliveries are logged

### Retry Policy

- **Initial delay**: 30 seconds
- **Max retries**: 10
- **Backoff**: Exponential (30s, 1m, 5m, 15m)
- **Permanent failure**: After 10 failed attempts

## Collections

### Followers Collection

```
GET https://loops.example.com/ap/users/123/followers
```

Paginated collection of follower actors.

### Following Collection

```
GET https://loops.example.com/ap/users/123/following
```

Paginated collection of actors the user follows.

### Outbox Collection

```
GET https://loops.example.com/ap/users/123/outbox
```

Paginated collection of user's public activities.

## Future Considerations

Planned federation features:

- **Reports/Flag**: Moderation reports across instances
- **Move**: Account migration support
- **Event**: Event federation support
- **Question**: Poll federation

## Testing Federation

### Tools

- **ActivityPub Validator**: Validate actor and activity JSON
- **Federation Tester**: Test delivery and receipt of activities
- **WebFinger Validator**: Verify WebFinger responses

### Manual Testing

Test federation with curl:

```bash
# Fetch actor
curl -H "Accept: application/activity+json" https://loops.example.com/users/123

# WebFinger lookup
curl https://loops.example.com/.well-known/webfinger?resource=acct:alice@loops.example.com
```

## Troubleshooting

### Common Issues

**Activities not delivering:**
- Check queue workers are running
- Verify HTTP signatures are valid
- Check remote instance isn't blocking your domain
- Review delivery logs

**Cannot fetch remote actors:**
- Verify remote instance is online
- Check DNS resolution
- Verify SSL certificates
- Check firewall rules

**WebFinger not working:**
- Ensure `.well-known` route is accessible
- Verify CORS headers if needed
- Check web server configuration
- Verify response format

## References

- [ActivityPub Specification](https://www.w3.org/TR/activitypub/)
- [ActivityStreams 2.0](https://www.w3.org/TR/activitystreams-core/)
- [WebFinger RFC 7033](https://tools.ietf.org/html/rfc7033)
- [HTTP Message Signatures RFC 9421](https://www.rfc-editor.org/rfc/rfc9421)
- [Digest Fields RFC 9530](https://www.rfc-editor.org/rfc/rfc9530)
- [HTTP Signatures (draft-cavage)](https://tools.ietf.org/html/draft-cavage-http-signatures)
