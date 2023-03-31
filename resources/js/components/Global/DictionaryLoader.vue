<template>
    <div :key="dictionaryLoading">
        <slot :dictionary="dictionary" :loading="dictionaryLoading"></slot>
    </div>
</template>

<script>
import {mapActions, mapState} from "vuex";

export default {
    name: "DictionaryLoader",
    props: {
        items: {type: Array, default: []},
    },
    computed: {
        ...mapState('dictionary',['dictionary', 'dictionaryLoading']),
    },
    mounted() {
        this.loadDictionary(this.items)
    },
    methods:{
        ...mapActions('dictionary', ['loadDictionary'])
    }
}
</script>

<style scoped>

</style>
