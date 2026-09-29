// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

/// @title OrderRegistry
/// @notice Stores a keccak256 fingerprint of each paid AMPUH order so anyone can
///         verify that the order data shown by the marketplace was not changed later.
contract OrderRegistry {
    struct Record {
        uint64 orderId;
        uint64 anchoredAt;
    }

    address public immutable owner;
    mapping(address => bool) public isRecorder;
    mapping(bytes32 => Record) private records;

    event Anchored(bytes32 indexed dataHash, uint64 indexed orderId, uint64 anchoredAt);
    event RecorderSet(address indexed recorder, bool allowed);

    error NotOwner();
    error NotRecorder();
    error AlreadyAnchored(bytes32 dataHash);
    error EmptyHash();

    constructor() {
        owner = msg.sender;
        isRecorder[msg.sender] = true;
        emit RecorderSet(msg.sender, true);
    }

    function setRecorder(address recorder, bool allowed) external {
        if (msg.sender != owner) revert NotOwner();
        isRecorder[recorder] = allowed;
        emit RecorderSet(recorder, allowed);
    }

    function anchor(bytes32 dataHash, uint64 orderId) external {
        if (!isRecorder[msg.sender]) revert NotRecorder();
        if (dataHash == bytes32(0)) revert EmptyHash();
        if (records[dataHash].anchoredAt != 0) revert AlreadyAnchored(dataHash);

        uint64 ts = uint64(block.timestamp);
        records[dataHash] = Record(orderId, ts);
        emit Anchored(dataHash, orderId, ts);
    }

    /// @return orderId the marketplace order id, 0 if unknown
    /// @return anchoredAt unix time the hash was stored, 0 if unknown
    function recordOf(bytes32 dataHash) external view returns (uint64 orderId, uint64 anchoredAt) {
        Record memory r = records[dataHash];
        return (r.orderId, r.anchoredAt);
    }
}
