// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import {Test} from "forge-std/Test.sol";
import {OrderRegistry} from "../src/OrderRegistry.sol";

contract OrderRegistryTest is Test {
    OrderRegistry registry;
    bytes32 constant H = keccak256("order-1");

    function setUp() public {
        registry = new OrderRegistry();
    }

    function test_anchorStoresRecord() public {
        vm.warp(1_700_000_000);
        registry.anchor(H, 42);
        (uint64 id, uint64 at) = registry.recordOf(H);
        assertEq(id, 42);
        assertEq(at, 1_700_000_000);
    }

    function test_unknownHashIsEmpty() public view {
        (uint64 id, uint64 at) = registry.recordOf(keccak256("nope"));
        assertEq(id, 0);
        assertEq(at, 0);
    }

    function test_revertsOnDuplicate() public {
        registry.anchor(H, 1);
        vm.expectRevert(abi.encodeWithSelector(OrderRegistry.AlreadyAnchored.selector, H));
        registry.anchor(H, 1);
    }

    function test_revertsForStranger() public {
        vm.prank(address(0xBEEF));
        vm.expectRevert(OrderRegistry.NotRecorder.selector);
        registry.anchor(H, 1);
    }

    function test_ownerCanAddRecorder() public {
        registry.setRecorder(address(0xBEEF), true);
        vm.prank(address(0xBEEF));
        registry.anchor(H, 7);
        (uint64 id,) = registry.recordOf(H);
        assertEq(id, 7);
    }
}
