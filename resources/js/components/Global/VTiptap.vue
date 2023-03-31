<template>
    <tiptap-vuetify v-model="input" :extensions="extensions"/>
</template>

<script>
import { TiptapVuetify, Heading, Bold, Italic, Strike, Underline, Code, Paragraph, BulletList, OrderedList, ListItem, Link, Blockquote, HardBreak, HorizontalRule, History } from 'tiptap-vuetify'

export default {
    name: "VTiptap",
    components: {TiptapVuetify},
    props: ['type','value', 'obj'],
    data: () => ({
        extensions: [
            History,
            Bold,
            Italic,
            Underline,
            Strike,
            Link,
            ListItem,
            BulletList,
            OrderedList,
            Blockquote,
            [Heading, {
                options: {
                    levels: [1, 2, 3]
                }
            }],
            // Code,
            // HorizontalRule,
            Paragraph,
            HardBreak
        ],
    }),
    computed:{
        input:{
            get(){  return this.value},
            set(val){ this.$emit('input', val)}  // listen to @input="handler"
        }
    }
}
</script>

<style scoped>

</style>
