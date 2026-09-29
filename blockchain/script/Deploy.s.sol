// SPDX-License-Identifier: MIT
pragma solidity ^0.8.28;

import {Script, console} from "forge-std/Script.sol";
import {OrderRegistry} from "../src/OrderRegistry.sol";

contract Deploy is Script {
    function run() external returns (OrderRegistry registry) {
        vm.startBroadcast();
        registry = new OrderRegistry();
        vm.stopBroadcast();
        console.log("OrderRegistry deployed at", address(registry));
    }
}
